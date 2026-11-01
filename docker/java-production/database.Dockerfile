FROM golang:1.27.3 AS gosu-build
# Keep gosu's upstream behavior, but replace its vulnerable bundled Go runtime.
# Pin the upstream 1.19 commit, verified by Go's checksum database.
# The compiler stays out of the final image.
RUN CGO_ENABLED=0 GOBIN=/out go install -trimpath github.com/tianon/gosu@6456aaa0f3c854d199d0f037f068eb97515b7513

FROM postgres:18-alpine3.24
COPY --from=gosu-build /out/gosu /usr/local/bin/gosu
# gosu initializes supplementary groups when dropping to postgres during init.
# Allow both service users to read group-protected, file-mounted secrets.
RUN addgroup -g 10001 holyspace-secrets \
    && addgroup postgres holyspace-secrets \
    && gosu --version \
    && gosu postgres sh -ec 'id -G | grep -Eq "(^| )10001( |$)"' \
    && apk upgrade --no-cache
COPY --chmod=755 docker/java-production/database-entrypoint.sh /usr/local/bin/holyspace-database-entrypoint
COPY --chmod=755 docker/java-production/database-runtime.sh /docker-entrypoint-initdb.d/00-holyspace-runtime.sh
ENTRYPOINT ["holyspace-database-entrypoint"]
CMD ["postgres"]
