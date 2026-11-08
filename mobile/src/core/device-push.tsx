import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { AppState, Platform } from "react-native";
import { router, useRootNavigationState } from "expo-router";
import type { MemberNotification } from "./models";
import { appName } from "./brand";
import { nativePush } from "./native-push";
import { installation, setPushOptIn } from "./push-installation";
import {
  canRenewPush,
  pushNotificationId,
  resolvePushDestination,
  type PushSettings,
} from "./push";
import { useSession } from "./session";
type PushContext = {
  supported: boolean;
  revision: number;
  busy: boolean;
  enable: () => Promise<void>;
  disable: () => Promise<void>;
};
const Context = createContext<PushContext | null>(null);
export function DevicePushProvider({ children }: { children: ReactNode }) {
  const { account, ready, api, isCurrentAccount, registerSignOutCleanup } =
    useSession();
  const navigation = useRootNavigationState();
  const [revision, setRevision] = useState(0),
    [busy, setBusy] = useState(false);
  const running = useRef(false),
    seen = useRef(new Set<string>());
  const sync = useCallback(
    async (explicit: boolean) => {
      if (!nativePush.supported || !account || running.current) return;
      const captured = account;
      running.current = true;
      setBusy(true);
      try {
        const local = await installation();
        if (!explicit && local.optedInAccount !== captured.id) return;
        const settings = await api<PushSettings>("/api/member/push");
        if (!isCurrentAccount(captured)) return;
        if (!settings.available || !settings.projectId) {
          if (explicit)
            throw new Error(
              "Your church is finishing device push setup. Your inbox remains available.",
            );
          return;
        }
        if (
          !explicit &&
          !canRenewPush(
            local.optedInAccount,
            captured.id,
            settings.devices,
            local.id,
          )
        ) {
          await setPushOptIn(null);
          return;
        }
        if (!(await nativePush.permission(explicit))) {
          await setPushOptIn(null);
          if (isCurrentAccount(captured))
            await api(`/api/member/push/devices/${local.id}`, "DELETE").catch(
              () => {},
            );
          if (explicit)
            throw new Error(
              `Notifications are not allowed. You can enable ${appName} notifications in your device settings.`,
            );
          return;
        }
        const token = await nativePush.token(settings.projectId);
        if (!isCurrentAccount(captured)) return;
        await api(`/api/member/push/devices/${local.id}`, "PUT", {
          installationSecret: local.secret,
          token,
          platform: Platform.OS,
          projectId: settings.projectId,
        });
        if (isCurrentAccount(captured)) {
          await setPushOptIn(captured.id);
          setRevision((value) => value + 1);
        }
      } catch (error) {
        if (explicit)
          throw new Error(
            error instanceof Error &&
              (error.message.startsWith("Your church") ||
                error.message.startsWith("Notifications are not allowed"))
              ? error.message
              : "Unable to enable device notifications. Check device permission and your church’s push setup, then try again.",
          );
      } finally {
        running.current = false;
        setBusy(false);
      }
    },
    [account, api, isCurrentAccount],
  );
  const disable = useCallback(async () => {
    if (!nativePush.supported) return;
    const captured = account;
    await setPushOptIn(null);
    await nativePush.clear();
    if (!captured || !nativePush.supported) return;
    const local = await installation();
    if (!isCurrentAccount(captured)) return;
    await api(`/api/member/push/devices/${local.id}`, "DELETE");
    if (isCurrentAccount(captured)) setRevision((value) => value + 1);
  }, [account, api, isCurrentAccount]);
  useEffect(
    () => registerSignOutCleanup(disable),
    [registerSignOutCleanup, disable],
  );
  useEffect(() => {
    if (!nativePush.supported || !ready) return;
    if (!account) {
      seen.current.clear();
      void nativePush.clear().catch(() => {});
      void setPushOptIn(null).catch(() => {});
      return;
    }
    const initial = setTimeout(() => void sync(false), 0);
    const foreground = AppState.addEventListener("change", (state) => {
      if (state === "active") void sync(false);
    });
    return () => {
      clearTimeout(initial);
      foreground.remove();
    };
  }, [account, ready, sync]);
  useEffect(() => {
    if (!nativePush.supported || !ready || !navigation?.key) return;
    const captured = account;
    let active = true;
    async function open(data: unknown) {
      const id = pushNotificationId(data);
      if (
        !id ||
        !captured ||
        !isCurrentAccount(captured) ||
        seen.current.has(id)
      )
        return;
      seen.current.add(id);
      const path = await resolvePushDestination(
        data,
        (id) => api<MemberNotification>(`/api/member/notifications/${id}`),
        () => active && isCurrentAccount(captured),
      );
      if (path) router.navigate(path);
      else seen.current.delete(id);
    }
    const remove = nativePush.listen(
      (data) => void open(data),
      () => {
        if (captured && isCurrentAccount(captured))
          setRevision((value) => value + 1);
      },
      () => void sync(false),
    );
    const last = nativePush.last();
    if (last) void open(last);
    return () => {
      active = false;
      remove();
    };
  }, [account, ready, navigation?.key, api, isCurrentAccount, sync]);
  return (
    <Context.Provider
      value={{
        supported: nativePush.supported,
        revision,
        busy,
        enable: () => sync(true),
        disable,
      }}
    >
      {children}
    </Context.Provider>
  );
}
export function useDevicePush() {
  const value = useContext(Context);
  if (!value) throw new Error("Device push provider is required.");
  return value;
}
