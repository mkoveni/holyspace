import { useCallback, useEffect, useRef, useState } from "react";
import { AppState } from "react-native";
import { useFocusEffect } from "expo-router";
export function useLoad<T>(
  loader: (key: string) => Promise<T>,
  deps: readonly unknown[] = [],
) {
  const loaderRef = useRef(loader);
  useEffect(() => {
    loaderRef.current = loader;
  }, [loader]);
  const generation = useRef(0),
    [data, setData] = useState<T | null>(null),
    [loading, setLoading] = useState(false),
    [error, setError] = useState("");
  const key = JSON.stringify(deps);
  const reload = useCallback(async () => {
    const run = ++generation.current;
    setLoading(true);
    setError("");
    try {
      const value = await loaderRef.current(key);
      if (run === generation.current) setData(value);
    } catch (e) {
      if (run === generation.current) {
        setData(null);
        setError(e instanceof Error ? e.message : "Unable to load this page.");
      }
    } finally {
      if (run === generation.current) setLoading(false);
    }
  }, [key]);
  // The key is supplied by each screen for its page parameters; data is never persisted.
  useFocusEffect(
    useCallback(() => {
      void reload();
      return () => {
        generation.current++;
      };
    }, [reload]),
  );
  useEffect(() => {
    const listener = AppState.addEventListener("change", (state) => {
      if (state === "active") void reload();
    });
    return () => listener.remove();
  }, [reload]);
  return { data, loading, error, reload };
}
