# Recover a small VPS after administrator bootstrap

The older bootstrap script started a second Java API alongside the regular API;
each allowed up to 2 GiB. This is a likely cause of the reported 1 GiB VPS crash.
Confirm an OOM event with the provider console or `sudo journalctl -k -b -1`
where persistent logs are available. An OOM kill can end a process before Java
has time to print an error. Do not delete database volumes or regenerate secrets.

If SSH is unavailable, recover/reboot through the VPS provider console. Then SSH
in and open a root Bash shell with `sudo -i`. On the VPS, stop the API:

```bash
cd /srv/holyspace/current
export JAVA_PRODUCTION_PROJECT=holyspace-java-prod
if [[ -f compose.java-production-nginx-app.yaml ]]; then
  export JAVA_PRODUCTION_TARGET=nginx-app
else
  export JAVA_PRODUCTION_TARGET=nginx-api
fi
source scripts/java-production/common.sh
dc stop api
```

For immediate recovery on an older deployed release, add a temporary API override.
This does not change database files or credentials:

```bash
cat > /srv/holyspace/shared/memory-recovery.yaml <<'YAML'
services:
  api:
    mem_limit: 512m
    memswap_limit: 512m
    environment:
      JAVA_TOOL_OPTIONS: -Xms32m -Xmx256m -XX:ActiveProcessorCount=1 -XX:+UseSerialGC -XX:+ExitOnOutOfMemoryError
      SERVER_TOMCAT_THREADS_MAX: 32
      SERVER_TOMCAT_THREADS_MIN_SPARE: 4
      SERVER_TOMCAT_MAX_CONNECTIONS: 128
      DB_POOL_SIZE: 5
YAML
chmod 600 /srv/holyspace/shared/memory-recovery.yaml
export JAVA_PRODUCTION_OVERRIDE_FILE=/srv/holyspace/shared/memory-recovery.yaml
```

If administrator creation did not finish, retry it while the ordinary API is
stopped. Replace the email; use the existing private password file, readable by
group 10001. If the account was committed before the crash, bootstrap refuses to
create another account; try logging in with the original credentials instead.

```bash
scripts/java-production/bootstrap.sh administrator admin@your-church.org \
  'Church Administrator' /srv/holyspace/secrets/bootstrap_password
```

After the administrator is created (or if it already exists), start the regular
API with the smaller budget. `--no-deps` preserves the existing database container:

```bash
dc up -d --no-deps --no-build --pull never --wait --wait-timeout 240 api
```

Remove the temporary password file only after successful account creation.
Deploy the updated release to apply all the permanent limits and the bootstrap
fix. The receiver does not carry this temporary override into future releases;
do not redeploy an older, uncapped release. Check memory with `free -h`, Docker
stats and `scripts/java-production/memory-status.sh` once available.

The bounded defaults are suitable for validation with modest traffic, not a
capacity promise. Increase the VPS to at least 2 GiB for production and monitor
under realistic load. Deployment scans now run sequentially with a 256 MiB
container cap and a 192 MiB Go memory target. Scanner OOMs still fail the security
gate; a 1 GiB host may need a maintenance window or more RAM to complete them.
