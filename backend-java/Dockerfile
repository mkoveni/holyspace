FROM maven:3.9-eclipse-temurin-25 AS build
WORKDIR /workspace
COPY pom.xml eclipse-formatter.xml ./
COPY .mvn .mvn
RUN --mount=type=cache,id=holyspace-maven,target=/root/.m2,sharing=locked mvn -B -ntp dependency:go-offline
COPY src ./src
RUN --mount=type=cache,id=holyspace-maven,target=/root/.m2,sharing=locked mvn -B -ntp -DskipTests package

FROM eclipse-temurin:25-jre
WORKDIR /app
# Java runs directly as PID 1; the base image's process manager is unused.
RUN if [ -f /usr/bin/pebble ]; then rm /usr/bin/pebble; fi \
    && apt-get update && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 10001 onechurch && useradd --uid 10001 --gid onechurch onechurch
COPY --from=build --chown=onechurch:onechurch /workspace/target/backend-java-*.jar app.jar
USER onechurch
EXPOSE 8082
ENTRYPOINT ["java", "-jar", "app.jar"]
