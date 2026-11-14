import { useState } from "react";
import { Text, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { openBrowserAsync } from "expo-web-browser";
import type { PaymentDetail } from "@/core/models";
import { useSession } from "@/core/session";
import { safeHandoff } from "@/core/http";
import { date, label, money } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import { Badge, Button, IconTile, Message, Screen } from "@/ui/components";
import { colors, fonts, ui } from "@/ui/theme";
export default function Payment() {
  const { id } = useLocalSearchParams<{ id: string }>(),
    session = useSession();
  const { data, loading, error, reload } = useLoad(
    () =>
      session.api<PaymentDetail>(
        `/api/my-giving/payments/${encodeURIComponent(id)}`,
      ),
    [id],
  );
  const [busy, setBusy] = useState(false),
    [actionError, setActionError] = useState("");
  const order = data?.order,
    canCheckout = order && ["PENDING", "READY"].includes(order.status);
  async function checkout() {
    if (!order || busy) return;
    setBusy(true);
    setActionError("");
    try {
      const result = await session.api<{ url: string }>(
        `/api/my-giving/payments/${order.id}/mobile-checkout`,
        "POST",
      );
      await openBrowserAsync(safeHandoff(result.url, session.origin), {
        toolbarColor: colors.green,
        showTitle: false,
      });
      await reload();
    } catch (e) {
      setActionError(
        e instanceof Error ? e.message : "Unable to open checkout.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      back={{
        label: "Back to giving",
        onPress: () => {
          if (router.canGoBack()) router.back();
          else router.replace("/giving");
        },
      }}
    >
      <Message text={error} error />
      <Message text={actionError} error />
      {order && (
        <>
          <View
            style={{
              backgroundColor: colors.hero,
              borderRadius: 26,
              paddingVertical: 26,
              paddingHorizontal: 20,
              alignItems: "center",
              gap: 8,
            }}
          >
            <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
              YOUR ONLINE GIFT
            </Text>
            <Text
              style={{
                fontFamily: fonts.display,
                fontSize: 44,
                lineHeight: 52,
                letterSpacing: -1,
                color: colors.onDark,
              }}
            >
              {money(order.amountMinor)}
            </Text>
            <Text
              style={[ui.caption, { color: colors.onDarkMuted, fontSize: 13 }]}
            >
              Yoco · One-off gift
            </Text>
            <View style={{ alignSelf: "center", marginTop: 4 }}>
              <Badge
                text={label(order.status)}
                tone={
                  order.status === "PAID"
                    ? "success"
                    : order.status === "FAILED" || order.status === "CANCELLED"
                      ? "danger"
                      : "gold"
                }
              />
            </View>
          </View>
          <View style={ui.card}>
            {order.mode === "test" && (
              <Message text="Test payment · No real contribution will be recorded." />
            )}
            {canCheckout && (
              <>
                <Button
                  title="Open secure checkout"
                  variant="gold"
                  icon="lock-closed"
                  busy={busy}
                  onPress={() => void checkout()}
                />
                <Text style={[ui.caption, { textAlign: "center" }]}>
                  After payment, close the browser to return here. Refresh to
                  see the provider-verified outcome.
                </Text>
              </>
            )}
          </View>
          {data.charges.map((c) => (
            <View key={c.id} style={ui.card}>
              <View
                style={{ flexDirection: "row", gap: 12, alignItems: "center" }}
              >
                <IconTile icon="receipt-outline" tone="success" />
                <Text style={[ui.h2, { flex: 1 }]}>{money(c.amountMinor)}</Text>
              </View>
              <Text style={ui.body}>
                {date(c.receivedOn)} · {label(c.givingStatus)}
              </Text>
              {c.refundedMinor > 0 && (
                <Text style={ui.body}>Refunded: {money(c.refundedMinor)}</Text>
              )}
              <Text style={[ui.body, { fontSize: 13 }]}>
                Confirmation:{" "}
                {c.captured
                  ? "Captured locally · not delivered"
                  : label(c.confirmationStatus)}
              </Text>
              {c.confirmation && (
                <View style={{ gap: 10 }}>
                  <Text style={{ fontWeight: "600", color: colors.text }}>
                    {c.confirmation.subject}
                  </Text>
                  <Text style={ui.body}>
                    {c.confirmation.html
                      .replace(/<[^>]*>/g, " ")
                      .replace(/&amp;/g, "&")
                      .replace(/&lt;/g, "<")
                      .replace(/&gt;/g, ">")
                      .replace(/&nbsp;/g, " ")
                      .replace(/\s+/g, " ")
                      .trim()}
                  </Text>
                  <Text style={[ui.body, { fontSize: 11 }]}>
                    Original payment confirmation. Later refunds appear
                    separately.
                  </Text>
                </View>
              )}
            </View>
          ))}
          {data.refunds.map((r, i) => (
            <View key={i} style={ui.card}>
              <View
                style={{ flexDirection: "row", gap: 12, alignItems: "center" }}
              >
                <IconTile icon="return-down-back-outline" tone="warning" />
                <Text style={[ui.h2, { flex: 1 }]}>
                  Refund · {money(r.amountMinor)}
                </Text>
              </View>
              <Text style={ui.body}>
                {date(r.refundedOn)} ·{" "}
                {r.applied ? "Recorded" : "Being recorded"}
              </Text>
            </View>
          ))}
        </>
      )}
    </Screen>
  );
}
