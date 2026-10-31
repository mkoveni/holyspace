FROM postgres:18
# gosu initializes supplementary groups when dropping to postgres during init.
# Allow both service users to read group-protected, file-mounted secrets.
RUN groupadd --gid 10001 holyspace-secrets \
    && usermod --append --groups holyspace-secrets postgres \
    && apt-get update && apt-get upgrade -y && rm -rf /var/lib/apt/lists/*
