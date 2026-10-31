import type { Account, GiftIntent } from "./models.ts";
import { createApi } from "./http.ts";
export interface CredentialStorage {
  read(key: string): Promise<string | null>;
  write(key: string, value: string): Promise<void>;
  remove(key: string): Promise<void>;
}
interface Snapshot {
  account: Account | null;
  ready: boolean;
  error: string;
  notice: string;
}
const sessionKey = "holyspace.member.session";
export class SessionStore {
  private token: string | null = null;
  private epoch = 0;
  private signOutCleanup: (() => Promise<void>) | null = null;
  private snapshot: Snapshot = {
    account: null,
    ready: false,
    error: "",
    notice: "",
  };
  private listeners = new Set<() => void>();
  private storageQueue: Promise<void> = Promise.resolve();
  readonly api: ReturnType<typeof createApi>;
  readonly origin: string;
  private storage: CredentialStorage;
  private deviceName: string;
  constructor(origin: string, storage: CredentialStorage, deviceName: string) {
    this.origin = origin;
    this.storage = storage;
    this.deviceName = deviceName;
    this.api = createApi(
      origin,
      () => this.token,
      () => this.clear("Your session expired. Please sign in again."),
    );
  }
  subscribe = (listener: () => void) => {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  };
  getSnapshot = () => this.snapshot;
  private publish(patch: Partial<Snapshot>) {
    this.snapshot = { ...this.snapshot, ...patch };
    this.listeners.forEach((listener) => listener());
  }
  private persist(value: string | null) {
    const operation = this.storageQueue
      .catch(() => {})
      .then(() =>
        value === null
          ? this.storage.remove(sessionKey)
          : this.storage.write(sessionKey, value),
      );
    this.storageQueue = operation;
    return operation;
  }
  clear = async (notice = "") => {
    const account = this.snapshot.account;
    this.epoch++;
    this.token = null;
    this.publish({ account: null, ready: true, error: "", notice });
    try {
      if (account) await this.storage.remove(`holyspace.pending.${account.id}`);
    } finally {
      await this.persist(null);
    }
  };
  retry = async () => {
    const run = ++this.epoch;
    this.publish({ ready: false, error: "" });
    try {
      const saved = await this.storage.read(sessionKey);
      if (run !== this.epoch) return;
      this.token = saved;
      if (saved) {
        const account = await this.api<Account>("/api/me");
        if (run === this.epoch) this.publish({ account });
      }
    } catch (error) {
      if (run === this.epoch)
        this.publish({
          error:
            error instanceof Error
              ? error.message
              : "Unable to restore your session.",
        });
    } finally {
      if (run === this.epoch) this.publish({ ready: true });
    }
  };
  signIn = async (identifier: string, password: string) => {
    const run = ++this.epoch;
    this.publish({ error: "", notice: "" });
    const result = await this.api<{ token: string }>(
      "/api/login_check",
      "POST",
      {
        username: identifier.trim(),
        password,
        deviceName: this.deviceName.slice(0, 80),
      },
    );
    if (run !== this.epoch) return;
    this.token = result.token;
    try {
      const account = await this.api<Account>("/api/me");
      if (run !== this.epoch) return;
      await this.persist(result.token);
      if (run === this.epoch) this.publish({ account, ready: true });
    } catch (error) {
      if (run === this.epoch) {
        try {
          await this.api("/api/logout", "POST");
        } catch {
          /* Always clear local credentials after a failed sign-in. */
        }
        await this.clear();
      }
      throw error;
    }
  };
  isCurrentAccount = (account: Account | null): boolean =>
    account !== null &&
    this.snapshot.account === account &&
    this.token !== null;
  registerSignOutCleanup = (cleanup: () => Promise<void>) => {
    this.signOutCleanup = cleanup;
    return () => {
      if (this.signOutCleanup === cleanup) this.signOutCleanup = null;
    };
  };
  signOut = async () => {
    const run = this.epoch;
    try {
      if (this.signOutCleanup) {
        let timer: ReturnType<typeof setTimeout> | undefined;
        try {
          await Promise.race([
            this.signOutCleanup().catch(() => {}),
            new Promise<void>((resolve) => {
              timer = setTimeout(resolve, 3000);
            }),
          ]);
        } finally {
          if (timer) clearTimeout(timer);
        }
      }
      if (this.token && run === this.epoch)
        await this.api("/api/logout", "POST");
    } finally {
      if (run === this.epoch) await this.clear();
    }
  };
  pending = async (): Promise<GiftIntent | null> => {
    const id = this.snapshot.account?.id;
    if (!id) return null;
    const raw = await this.storage.read(`holyspace.pending.${id}`);
    if (id !== this.snapshot.account?.id || !raw) return null;
    try {
      const parsed = JSON.parse(raw);
      if (
        typeof parsed.requestId !== "string" ||
        typeof parsed.fundId !== "string" ||
        typeof parsed.provider !== "string" ||
        !Number.isSafeInteger(parsed.amountMinor) ||
        typeof parsed.recurring !== "boolean"
      )
        return null;
      return parsed as GiftIntent;
    } catch {
      return null;
    }
  };
  savePending = async (gift: GiftIntent | null) => {
    const id = this.snapshot.account?.id;
    if (!id) return;
    if (gift)
      await this.storage.write(`holyspace.pending.${id}`, JSON.stringify(gift));
    else await this.storage.remove(`holyspace.pending.${id}`);
  };
}
