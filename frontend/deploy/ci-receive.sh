#!/usr/bin/env bash
# Forced command for the CI deploy key (installed as /usr/local/sbin/onechurch-web-receive by setup-server.sh).
# The key can do exactly two things, whatever command the client asks for:
#   ssh onechurch-web-deploy@host "deploy <release-id>" < release.tar.gz    install a release (archive on stdin)
#   ssh onechurch-web-deploy@host version                                   checksums of the installed deploy scripts
set -euo pipefail

read -r -a request <<< "${SSH_ORIGINAL_COMMAND:-}"
case "${request[0]:-}" in
    deploy)
        [[ ${#request[@]} -eq 2 && "${request[1]}" =~ ^[0-9]{14}-[0-9a-f]{12}$ ]] || { echo "Usage: deploy <release-id>" >&2; exit 2; }
        exec sudo -n /usr/local/sbin/onechurch-web-install --stdin "${request[1]}"
        ;;
    version)
        printf 'install=%s\nreceive=%s\n' \
            "$(sha256sum /usr/local/sbin/onechurch-web-install | cut -d' ' -f1)" \
            "$(sha256sum /usr/local/sbin/onechurch-web-receive | cut -d' ' -f1)"
        ;;
    *)
        echo "Allowed commands: deploy <release-id>, version" >&2
        exit 2
        ;;
esac
