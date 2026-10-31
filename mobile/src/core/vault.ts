import { Platform } from "react-native";
import {
  deleteItemAsync,
  getItemAsync,
  setItemAsync,
  WHEN_UNLOCKED_THIS_DEVICE_ONLY,
} from "expo-secure-store";
const memory = new Map<string, string>();
export const vault = {
  read: (key: string): Promise<string | null> =>
    Platform.OS === "web"
      ? Promise.resolve(memory.get(key) || null)
      : getItemAsync(key),
  write: async (key: string, value: string): Promise<void> => {
    if (Platform.OS === "web") {
      memory.set(key, value);
      return;
    }
    await setItemAsync(key, value, {
      keychainAccessible: WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    });
  },
  remove: async (key: string): Promise<void> => {
    if (Platform.OS === "web") {
      memory.delete(key);
      return;
    }
    await deleteItemAsync(key);
  },
};
