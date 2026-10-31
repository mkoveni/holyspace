// Web previews never request native permissions or hold device push tokens.
export const nativePush = {
  supported: false,
  permission: async (_request = false) => false,
  token: async (_project: string) => "",
  clear: async () => {},
  listen:
    (
      _listener: (data: unknown) => void,
      _received: () => void,
      _refresh: () => void,
    ) =>
    () => {},
  last: (): unknown => null,
};
