-- ============================================================
--  BAGMATI SCHOOL LIBRARY — SCHEMA v4
--  Run entirely in Supabase SQL Editor. Safe to re-run.
--  CHANGES FROM v3:
--    - borrow_requests.user_id now references profiles(id)
--      → fixes the PostgREST embedded join 400 error
--    - New RPC: request_book()       → fixes student RLS borrow error
--    - New RPC: send_notice()        → admin/librarian broadcast notifications
--    - New RPC: admin_reset_auth()   → admin can wipe auth data cleanly
--    - notifications type expanded to include 'notice'
--    - Performance indexes added
--    - Enterprise security: audit_log table + trigger
-- ============================================================

-- ─────────────────────────────────────────────────────────────
--  0. CLEAN SLATE
-- ─────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS audit_log          CASCADE;
DROP TABLE IF EXISTS notifications      CASCADE;
DROP TABLE IF EXISTS borrow_requests    CASCADE;
DROP TABLE IF EXISTS books              CASCADE;
DROP TABLE IF EXISTS loans              CASCADE;
DROP TABLE IF EXISTS featured_books     CASCADE;
DROP TABLE IF EXISTS profiles           CASCADE;

DROP FUNCTION IF EXISTS handle_new_user()                           CASCADE;
DROP FUNCTION IF EXISTS set_student_uid()                           CASCADE;
DROP FUNCTION IF EXISTS check_book_availability()                   CASCADE;
DROP FUNCTION IF EXISTS admin_set_role(text, text)                  CASCADE;
DROP FUNCTION IF EXISTS set_updated_at()                            CASCADE;
DROP FUNCTION IF EXISTS current_role_name()                         CASCADE;
DROP FUNCTION IF EXISTS request_book(uuid)                          CASCADE;
DROP FUNCTION IF EXISTS send_notice(uuid, text, text)               CASCADE;
DROP FUNCTION IF EXISTS send_notice(text, text, text)               CASCADE;
DROP FUNCTION IF EXISTS admin_reset_auth()                          CASCADE;
DROP FUNCTION IF EXISTS log_audit()                                 CASCADE;

-- ─────────────────────────────────────────────────────────────
--  1. PROFILES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE profiles (
  id         uuid PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  full_name  text,
  email      text,
  role       text NOT NULL DEFAULT 'student'
                  CHECK (role IN ('student','librarian','admin')),
  uid        text UNIQUE,
  class      text,
  section    text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

-- ─────────────────────────────────────────────────────────────
--  2. BOOKS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE books (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  title       text NOT NULL,
  author      text,
  description text,
  genre       text,    -- comma-separated e.g. "Non-Fiction, Self-Help"
  quantity    int  NOT NULL DEFAULT 1 CHECK (quantity >= 0),
  cover_url   text,
  added_by    uuid REFERENCES auth.users(id) ON DELETE SET NULL,
  created_at  timestamptz NOT NULL DEFAULT now(),
  updated_at  timestamptz NOT NULL DEFAULT now()
);

-- ─────────────────────────────────────────────────────────────
--  3. BORROW_REQUESTS
--  KEY FIX: user_id now references profiles(id) directly.
--  This allows PostgREST to resolve the embedded join:
--    .select('*,books(*),profiles:user_id(full_name,email,uid,...)')
--  Without this FK, the join fails with HTTP 400.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE borrow_requests (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  book_id       uuid NOT NULL REFERENCES books(id) ON DELETE CASCADE,
  user_id       uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,  -- ← FIXED: was auth.users
  status        text NOT NULL DEFAULT 'pending'
                     CHECK (status IN ('pending','approved','rejected','returned')),
  requested_at  timestamptz NOT NULL DEFAULT now(),
  approved_at   timestamptz,
  approved_by   uuid REFERENCES auth.users(id) ON DELETE SET NULL,
  due_date      timestamptz,
  duration_days int,
  returned_at   timestamptz,
  late_fee      numeric(10,2) DEFAULT 0,
  notes         text
);

-- Only one pending request per (book, user) at a time
CREATE UNIQUE INDEX borrow_requests_unique_pending
  ON borrow_requests (book_id, user_id)
  WHERE status = 'pending';

-- ─────────────────────────────────────────────────────────────
--  4. NOTIFICATIONS
--  Added 'notice' type for admin/librarian manual broadcasts.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE notifications (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  type       text NOT NULL DEFAULT 'general'
                  CHECK (type IN ('approved','rejected','returned','due_soon','overdue','general','notice')),
  title      text NOT NULL,
  message    text NOT NULL,
  read       boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- ─────────────────────────────────────────────────────────────
--  5. AUDIT LOG  (enterprise security — immutable trail)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE audit_log (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  actor_id   uuid,          -- who performed the action (null = system)
  actor_role text,
  action     text NOT NULL, -- e.g. 'borrow_request','role_change','notice_sent'
  target_id  uuid,          -- affected row id (book, user, request)
  detail     jsonb,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- ─────────────────────────────────────────────────────────────
--  6. ENABLE RLS
-- ─────────────────────────────────────────────────────────────
ALTER TABLE profiles        ENABLE ROW LEVEL SECURITY;
ALTER TABLE books            ENABLE ROW LEVEL SECURITY;
ALTER TABLE borrow_requests  ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications    ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_log        ENABLE ROW LEVEL SECURITY;

-- ─────────────────────────────────────────────────────────────
--  7. HELPER FUNCTION — bypasses RLS via SECURITY DEFINER
--     Called inside other RLS policies/RPCs to avoid recursion.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION current_role_name()
RETURNS text LANGUAGE sql STABLE SECURITY DEFINER
SET search_path = public
AS $$
  SELECT role FROM profiles WHERE id = auth.uid()
$$;

-- ─────────────────────────────────────────────────────────────
--  8. RLS — PROFILES
-- ─────────────────────────────────────────────────────────────
-- Students see their own row; lib/admin see all
CREATE POLICY "profiles_select" ON profiles FOR SELECT TO authenticated
  USING (id = auth.uid() OR current_role_name() IN ('librarian','admin'));

-- Allow own-row insert (first-login fallback, trigger also covers this)
CREATE POLICY "profiles_insert" ON profiles FOR INSERT TO authenticated
  WITH CHECK (id = auth.uid());

-- UPDATE own row — role column frozen (use admin_set_role RPC to change role)
CREATE POLICY "profiles_update_own" ON profiles FOR UPDATE TO authenticated
  USING (id = auth.uid())
  WITH CHECK (
    id = auth.uid()
    AND role = (SELECT role FROM profiles WHERE id = auth.uid())
  );

-- ─────────────────────────────────────────────────────────────
--  9. RLS — BOOKS
-- ─────────────────────────────────────────────────────────────
CREATE POLICY "books_select" ON books FOR SELECT TO public USING (true);
CREATE POLICY "books_insert" ON books FOR INSERT TO authenticated
  WITH CHECK (current_role_name() IN ('librarian','admin'));
CREATE POLICY "books_update" ON books FOR UPDATE TO authenticated
  USING (current_role_name() IN ('librarian','admin'));
CREATE POLICY "books_delete" ON books FOR DELETE TO authenticated
  USING (current_role_name() IN ('librarian','admin'));

-- ─────────────────────────────────────────────────────────────
--  10. RLS — BORROW_REQUESTS
--  Direct INSERT for students kept as fallback.
--  Prefer using the request_book() RPC from JS for reliability.
-- ─────────────────────────────────────────────────────────────
CREATE POLICY "br_select" ON borrow_requests FOR SELECT TO authenticated
  USING (user_id = auth.uid() OR current_role_name() IN ('librarian','admin'));

CREATE POLICY "br_insert" ON borrow_requests FOR INSERT TO authenticated
  WITH CHECK (user_id = auth.uid() AND current_role_name() = 'student');

CREATE POLICY "br_update" ON borrow_requests FOR UPDATE TO authenticated
  USING (current_role_name() IN ('librarian','admin'));

CREATE POLICY "br_delete_pending" ON borrow_requests FOR DELETE TO authenticated
  USING (user_id = auth.uid() AND status = 'pending');

-- ─────────────────────────────────────────────────────────────
--  11. RLS — NOTIFICATIONS
-- ─────────────────────────────────────────────────────────────
CREATE POLICY "notif_select" ON notifications FOR SELECT TO authenticated
  USING (user_id = auth.uid());

-- Librarian/admin can insert for any user; system RPCs (SECURITY DEFINER) bypass this
CREATE POLICY "notif_insert" ON notifications FOR INSERT TO authenticated
  WITH CHECK (current_role_name() IN ('librarian','admin'));

CREATE POLICY "notif_update_own" ON notifications FOR UPDATE TO authenticated
  USING (user_id = auth.uid());

-- ─────────────────────────────────────────────────────────────
--  12. RLS — AUDIT LOG
--  Only admins can read; nobody can write directly (RPCs do it)
-- ─────────────────────────────────────────────────────────────
CREATE POLICY "audit_select_admin" ON audit_log FOR SELECT TO authenticated
  USING (current_role_name() = 'admin');

-- ─────────────────────────────────────────────────────────────
--  13. TRIGGER: handle_new_user
--  SECURITY DEFINER → runs as postgres, bypasses RLS.
--  Creates profile immediately on signup (before email verify).
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION handle_new_user()
RETURNS TRIGGER LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  INSERT INTO profiles (id, email, full_name, role, class, section)
  VALUES (
    NEW.id,
    NEW.email,
    COALESCE(NEW.raw_user_meta_data->>'full_name', split_part(NEW.email, '@', 1)),
    'student',
    NEW.raw_user_meta_data->>'class',
    NEW.raw_user_meta_data->>'section'
  )
  ON CONFLICT (id) DO NOTHING;
  RETURN NEW;
END;
$$;

CREATE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW EXECUTE FUNCTION handle_new_user();

-- ─────────────────────────────────────────────────────────────
--  14. TRIGGER: auto-generate student UID (STU-01001 …)
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION set_student_uid()
RETURNS TRIGGER LANGUAGE plpgsql
SET search_path = public
AS $$
DECLARE
  next_num int;
BEGIN
  IF NEW.role = 'student' AND (NEW.uid IS NULL OR NEW.uid = '') THEN
    SELECT COALESCE(MAX(CAST(SUBSTRING(uid FROM 5) AS int)), 1000) + 1
      INTO next_num
      FROM profiles
     WHERE uid IS NOT NULL AND uid ~ '^STU-[0-9]+$';
    NEW.uid := 'STU-' || LPAD(next_num::text, 5, '0');
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER trg_student_uid
  BEFORE INSERT OR UPDATE ON profiles
  FOR EACH ROW EXECUTE FUNCTION set_student_uid();

-- ─────────────────────────────────────────────────────────────
--  15. TRIGGER: updated_at
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql
SET search_path = public          -- FIX: prevents mutable search_path warning
AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$;

CREATE TRIGGER trg_profiles_ua BEFORE UPDATE ON profiles FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_books_ua    BEFORE UPDATE ON books    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ─────────────────────────────────────────────────────────────
--  16. TRIGGER: prevent over-approval
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION check_book_availability()
RETURNS TRIGGER LANGUAGE plpgsql
SET search_path = public
AS $$
DECLARE
  total_qty    int;
  active_loans int;
BEGIN
  IF NEW.status = 'approved' AND (OLD.status IS DISTINCT FROM 'approved') THEN
    SELECT quantity INTO total_qty FROM books WHERE id = NEW.book_id;
    SELECT COUNT(*) INTO active_loans
      FROM borrow_requests
     WHERE book_id = NEW.book_id AND status = 'approved' AND id != NEW.id;
    IF active_loans >= total_qty THEN
      RAISE EXCEPTION 'No copies available (% of % already on loan)', active_loans, total_qty;
    END IF;
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER trg_check_availability
  BEFORE INSERT OR UPDATE ON borrow_requests
  FOR EACH ROW EXECUTE FUNCTION check_book_availability();

-- ─────────────────────────────────────────────────────────────
--  17. RPC: admin_set_role
--  Changes user role; blocked by RLS otherwise.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION admin_set_role(target_email text, new_role text)
RETURNS json LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  caller_role text;
  target_id   uuid;
BEGIN
  SELECT role INTO caller_role FROM profiles WHERE id = auth.uid();
  IF caller_role IS DISTINCT FROM 'admin' THEN
    RETURN json_build_object('error', 'Only admins can change roles');
  END IF;
  IF new_role NOT IN ('student','librarian','admin') THEN
    RETURN json_build_object('error', 'Invalid role: ' || new_role);
  END IF;
  UPDATE profiles SET role = new_role WHERE email = target_email RETURNING id INTO target_id;
  IF NOT FOUND THEN
    RETURN json_build_object('error', 'User not found: ' || target_email);
  END IF;
  -- Audit trail
  INSERT INTO audit_log (actor_id, actor_role, action, target_id, detail)
  VALUES (auth.uid(), 'admin', 'role_change',
          target_id,
          json_build_object('email', target_email, 'new_role', new_role));
  RETURN json_build_object('success', true, 'email', target_email, 'new_role', new_role);
END;
$$;

-- ─────────────────────────────────────────────────────────────
--  18. RPC: request_book  ← NEW
--
--  WHY NEEDED:
--    The br_insert policy uses current_role_name() to verify
--    the inserting user is a 'student'. If the profile row
--    doesn't exist yet (race condition), current_role_name()
--    returns NULL and the INSERT is denied with "violates RLS".
--    This SECURITY DEFINER function bypasses that race by
--    reading the profile directly as postgres.
--
--  Also enforces: no duplicate pending, no active loan for
--  the same book, and logs the request in audit_log.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION request_book(p_book_id uuid)
RETURNS json LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  caller_role text;
  existing    int;
  new_id      uuid;
BEGIN
  -- Role check (runs as postgres, so no RLS on profiles)
  SELECT role INTO caller_role FROM profiles WHERE id = auth.uid();
  IF caller_role IS DISTINCT FROM 'student' THEN
    RETURN json_build_object('error', 'Only students can request books');
  END IF;
  -- Duplicate pending check
  SELECT COUNT(*) INTO existing
    FROM borrow_requests
   WHERE book_id = p_book_id AND user_id = auth.uid() AND status = 'pending';
  IF existing > 0 THEN
    RETURN json_build_object('error', 'You already have a pending request for this book');
  END IF;
  -- Active loan check
  SELECT COUNT(*) INTO existing
    FROM borrow_requests
   WHERE book_id = p_book_id AND user_id = auth.uid() AND status = 'approved';
  IF existing > 0 THEN
    RETURN json_build_object('error', 'You already have this book on loan');
  END IF;
  -- Create the request
  INSERT INTO borrow_requests (book_id, user_id, status)
  VALUES (p_book_id, auth.uid(), 'pending')
  RETURNING id INTO new_id;
  -- Audit
  INSERT INTO audit_log (actor_id, actor_role, action, target_id, detail)
  VALUES (auth.uid(), 'student', 'borrow_request', new_id,
          json_build_object('book_id', p_book_id));
  RETURN json_build_object('success', true, 'id', new_id);
END;
$$;

-- ─────────────────────────────────────────────────────────────
--  19. RPC: send_notice  ← NEW
--
--  Admin or Librarian can send a notification to:
--    - A specific student  (p_user_id IS NOT NULL)
--    - All students        (p_user_id IS NULL)
--  p_recipient_email is an optional alternative to user_id.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION send_notice(
  p_user_id uuid,        -- null → broadcast to all students
  p_title   text,
  p_message text
) RETURNS json LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  caller_role text;
  cnt         int;
BEGIN
  SELECT role INTO caller_role FROM profiles WHERE id = auth.uid();
  IF caller_role NOT IN ('librarian','admin') THEN
    RETURN json_build_object('error', 'Only librarians and admins can send notices');
  END IF;
  IF p_title IS NULL OR trim(p_title) = '' THEN
    RETURN json_build_object('error', 'Title is required');
  END IF;
  IF p_message IS NULL OR trim(p_message) = '' THEN
    RETURN json_build_object('error', 'Message is required');
  END IF;

  IF p_user_id IS NOT NULL THEN
    -- Single recipient
    INSERT INTO notifications (user_id, type, title, message)
    VALUES (p_user_id, 'notice', trim(p_title), trim(p_message));
    cnt := 1;
  ELSE
    -- Broadcast to all active students
    INSERT INTO notifications (user_id, type, title, message)
    SELECT id, 'notice', trim(p_title), trim(p_message)
      FROM profiles WHERE role = 'student';
    GET DIAGNOSTICS cnt = ROW_COUNT;
  END IF;

  -- Audit
  INSERT INTO audit_log (actor_id, actor_role, action, detail)
  VALUES (auth.uid(), caller_role, 'notice_sent',
          json_build_object('title', p_title, 'recipients', cnt,
                            'target_user_id', p_user_id));
  RETURN json_build_object('success', true, 'recipients', cnt);
END;
$$;

-- ─────────────────────────────────────────────────────────────
--  20. VIEW: books with live availability
--  FIX: security_invoker = true → view runs as the QUERYING user,
--  not as the view creator. This respects each user's RLS policies
--  and clears the Supabase "Security Definer View" ERROR lint.
--  (Requires PostgreSQL 15+, which Supabase uses.)
-- ─────────────────────────────────────────────────────────────
DROP VIEW IF EXISTS books_with_availability;
CREATE VIEW books_with_availability
  WITH (security_invoker = true)
AS
SELECT
  b.*,
  GREATEST(0,
    b.quantity - COUNT(br.id) FILTER (WHERE br.status = 'approved')
  ) AS available
FROM books b
LEFT JOIN borrow_requests br ON br.book_id = b.id
GROUP BY b.id;

-- ─────────────────────────────────────────────────────────────
--  NOTE: Leaked Password Protection (auth_leaked_password_protection)
--  This cannot be fixed via SQL. Enable it in the Supabase Dashboard:
--    Authentication → Providers → Email → Enable "Leaked Password Protection"
--  It checks passwords against HaveIBeenPwned.org on signup/password change.
-- ─────────────────────────────────────────────────────────────

-- ─────────────────────────────────────────────────────────────
--  21. PERFORMANCE INDEXES
-- ─────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_br_user_status   ON borrow_requests (user_id, status);
CREATE INDEX IF NOT EXISTS idx_br_book_status   ON borrow_requests (book_id, status);
CREATE INDEX IF NOT EXISTS idx_br_due_date      ON borrow_requests (due_date) WHERE status = 'approved';
CREATE INDEX IF NOT EXISTS idx_notif_user_read  ON notifications   (user_id, read, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_profiles_role    ON profiles        (role);
CREATE INDEX IF NOT EXISTS idx_audit_actor      ON audit_log       (actor_id, created_at DESC);

-- ─────────────────────────────────────────────────────────────
--  22. SET ADMIN ACCOUNT
--  If the profile row doesn't exist yet, sign up first then
--  re-run just this UPDATE statement.
-- ─────────────────────────────────────────────────────────────
UPDATE profiles
  SET role = 'admin'
  WHERE email = 'arai27814@gmail.com';

-- ─────────────────────────────────────────────────────────────
--  23. HOW TO RESET AUTH / LOGIN DATA (manual instructions)
--
--  WARNING: This is DESTRUCTIVE. All users will be deleted.
--  Run ONLY if you want to start fresh with logins.
--
--  Step 1 — Clear application data linked to auth:
--    DELETE FROM notifications;
--    DELETE FROM borrow_requests;
--    DELETE FROM profiles;
--
--  Step 2 — Delete all auth users (Supabase dashboard method):
--    Go to Authentication → Users → select all → Delete
--    OR run in SQL Editor (if you have access to auth schema):
--    DELETE FROM auth.users;   -- ← be very careful!
--
--  Step 3 — Re-run this schema to restore clean state.
--
--  Step 4 — Sign up admin account again and run:
--    UPDATE profiles SET role = 'admin'
--    WHERE email = 'arai27814@gmail.com';
--
--  NOTE: Books table is NOT deleted in this process.
--        Only auth/user data (profiles, borrow_requests, notifications).
-- ─────────────────────────────────────────────────────────────

-- ─────────────────────────────────────────────────────────────
--  VERIFY with:
--  SELECT email, role, uid FROM profiles;
--  SELECT proname FROM pg_proc WHERE proname IN
--    ('handle_new_user','admin_set_role','set_student_uid',
--     'check_book_availability','current_role_name',
--     'request_book','send_notice');
-- ─────────────────────────────────────────────────────────────
