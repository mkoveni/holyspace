import {
  createContext,
  useContext,
  useEffect,
  useState,
  useSyncExternalStore,
  type ReactNode,
} from "react";
import { Platform } from "react-native";
import { deviceName, modelName } from "expo-device";
import { apiOrigin } from "./http";
import { appName } from "./brand";
import { SessionStore } from "./session-store";
import { vault } from "./vault";
const origin = apiOrigin(
  process.env.EXPO_PUBLIC_API_URL || "http://localhost:8082",
  __DEV__,
);
type Session = ReturnType<SessionStore["getSnapshot"]> &
  Pick<
    SessionStore,
    | "api"
    | "origin"
    | "signIn"
    | "signOut"
    | "clear"
    | "retry"
    | "pending"
    | "savePending"
    | "isCurrentAccount"
    | "registerSignOutCleanup"
  >;
const Context = createContext<Session | null>(null);
export function SessionProvider({ children }: { children: ReactNode }) {
  const [store] = useState(
    () =>
      new SessionStore(
        origin,
        vault,
        `${appName} · ${Platform.OS === "web" ? "Web preview" : deviceName || modelName || Platform.OS}`,
      ),
  );
  const state = useSyncExternalStore(
    store.subscribe,
    store.getSnapshot,
    store.getSnapshot,
  );
  useEffect(() => {
    void store.retry();
  }, [store]);
  return (
    <Context.Provider
      value={{
        ...state,
        api: store.api,
        isCurrentAccount: store.isCurrentAccount,
        registerSignOutCleanup: store.registerSignOutCleanup,
        origin: store.origin,
        signIn: store.signIn,
        signOut: store.signOut,
        clear: store.clear,
        retry: store.retry,
        pending: store.pending,
        savePending: store.savePending,
      }}
    >
      {children}
    </Context.Provider>
  );
}
export function useSession() {
  const value = useContext(Context);
  if (!value) throw new Error("Session provider is required.");
  return value;
}
