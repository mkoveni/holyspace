import { Platform } from "react-native";
import {
  AndroidImportance,
  IosAuthorizationStatus,
  addNotificationResponseReceivedListener,
  addNotificationReceivedListener,
  addPushTokenListener,
  getExpoPushTokenAsync,
  getPermissionsAsync,
  requestPermissionsAsync,
  setNotificationChannelAsync,
  setNotificationHandler,
  getLastNotificationResponse,
  clearLastNotificationResponse,
  dismissAllNotificationsAsync,
} from "expo-notifications";
setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: false,
    shouldShowList: false,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});
export const nativePush = {
  supported: true,
  async permission(request = false): Promise<boolean> {
    if (Platform.OS === "android")
      await setNotificationChannelAsync("member-updates", {
        name: "Member updates",
        importance: AndroidImportance.DEFAULT,
        lockscreenVisibility: 0,
      });
    let result = await getPermissionsAsync();
    if (request && !result.granted && result.canAskAgain)
      result = await requestPermissionsAsync({
        ios: { allowAlert: true, allowSound: true, allowBadge: false },
      });
    return (
      result.granted ||
      result.ios?.status === IosAuthorizationStatus.PROVISIONAL
    );
  },
  async token(project: string): Promise<string> {
    return (await getExpoPushTokenAsync({ projectId: project })).data;
  },
  async clear() {
    clearLastNotificationResponse();
    await dismissAllNotificationsAsync();
  },
  listen(
    listener: (data: unknown) => void,
    received: () => void,
    refresh: () => void,
  ) {
    const response = addNotificationResponseReceivedListener((event) => {
      clearLastNotificationResponse();
      listener(event.notification.request.content.data);
    });
    const incoming = addNotificationReceivedListener((event) => {
      if (
        event.request.content.data?.type ===
        "divinecommunityspace.member-update.v1"
      )
        received();
    });
    const token = addPushTokenListener(() => refresh());
    return () => {
      response.remove();
      incoming.remove();
      token.remove();
    };
  },
  last(): unknown {
    const response = getLastNotificationResponse();
    clearLastNotificationResponse();
    return response?.notification.request.content.data ?? null;
  },
};
