# The Java API contract, served by the Symfony port (backend-symfony, :8083), which
# matches the Java routes one for one. To reach the original Java API instead, run
# with VITE_API_PROXY_TARGET=http://localhost:8082.
VITE_BACKEND=java
VITE_API_BASE_URL=/
VITE_API_PROXY_TARGET=http://localhost:8083
