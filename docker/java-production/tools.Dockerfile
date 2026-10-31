FROM alpine:3.22
RUN apk upgrade --no-cache && apk add --no-cache age
ENTRYPOINT []
