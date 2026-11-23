# Requires compose_cli, log, DB_USER and DB_NAME.
prepare_installation_state() {
INSTALL_MARKER_EXISTS="$(compose_cli exec -T db psql -v ON_ERROR_STOP=1 -U "${DB_USER}" -d "${DB_NAME}" -tAc "SELECT to_regclass('public.webino_installation_state') IS NOT NULL" | tr -d '[:space:]')"
if [ "${INSTALL_MARKER_EXISTS}" != "t" ]; then
  TABLE_COUNT="$(compose_cli exec -T db psql -v ON_ERROR_STOP=1 -U "${DB_USER}" -d "${DB_NAME}" -tAc "SELECT count(*) FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname NOT IN ('pg_catalog', 'information_schema') AND left(n.nspname, 3) <> 'pg_' AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')" | tr -d '[:space:]')"
  if [ "${TABLE_COUNT}" = "0" ]; then
    log "Preparing new installation state"
    compose_cli exec -T db psql -v ON_ERROR_STOP=1 -U "${DB_USER}" -d "${DB_NAME}" <<'SQL'
BEGIN;
SELECT pg_advisory_xact_lock(756204019);
DO $installation$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname NOT IN ('pg_catalog', 'information_schema') AND left(n.nspname, 3) <> 'pg_' AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')) THEN
    RAISE EXCEPTION 'database is not empty';
  END IF;
END
$installation$;
CREATE TABLE public.webino_installation_state (
  id smallint PRIMARY KEY CHECK (id = 1),
  status varchar(16) NOT NULL CHECK (status IN ('pending', 'completed'))
);
INSERT INTO public.webino_installation_state (id, status) VALUES (1, 'pending');
COMMIT;
SQL
    INSTALL_MARKER_EXISTS=t
  fi
fi
INSTALL_PENDING=0
if [ "${INSTALL_MARKER_EXISTS}" = "t" ]; then
  INSTALL_STATE="$(compose_cli exec -T db psql -v ON_ERROR_STOP=1 -U "${DB_USER}" -d "${DB_NAME}" -tAc "SELECT status FROM public.webino_installation_state WHERE id = 1" | tr -d '[:space:]')"
  if [ "${INSTALL_STATE}" = "pending" ]; then
    INSTALL_PENDING=1
  elif [ "${INSTALL_STATE}" != "completed" ]; then
    echo "ERROR: invalid installation state; no initialization was performed." >&2
    return 1
  fi
fi

}
