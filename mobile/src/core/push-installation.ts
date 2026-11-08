import { randomUUID } from "expo-crypto";
import { vault } from "./vault";
type Installation = {
  id: string;
  secret: string;
  optedInAccount: string | null;
};
const key = "divinecommunityspace.push.installation";
let pending: Promise<Installation> | null = null;
let writes: Promise<void> = Promise.resolve();
export function installation(): Promise<Installation> {
  if (!pending)
    pending = (async () => {
      const raw = await vault.read(key);
      if (raw) {
        try {
          const value = JSON.parse(raw) as Installation;
          if (
            typeof value.id === "string" &&
            typeof value.secret === "string" &&
            /^[a-f0-9]{64}$/.test(value.secret)
          )
            return value;
        } catch {
          /* Replace an invalid local installation value without logging it. */
        }
      }
      const value = {
        id: randomUUID(),
        secret: (randomUUID() + randomUUID()).replaceAll("-", ""),
        optedInAccount: null,
      };
      await vault.write(key, JSON.stringify(value));
      return value;
    })().catch((error) => {
      pending = null;
      throw error;
    });
  return pending;
}
export async function setPushOptIn(account: string | null): Promise<void> {
  const value = await installation();
  value.optedInAccount = account;
  writes = writes
    .catch(() => {})
    .then(() => vault.write(key, JSON.stringify(value)));
  await writes;
}
