import { useState } from "react";
import { Text, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { openBrowserAsync } from "expo-web-browser";
import type { PaymentDetail } from "@/core/models";
import { useSession } from "@/core/session";
import { safeHandoff } from "@/core/http";
import { date, label, money } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import { Button, Check, Message, Screen, Sheet } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
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
    [actionError, setActionError] = useState(""),
    [cancelOpen, setCancelOpen] = useState(false),
    [confirmed, setConfirmed] = useState(false);
  const order = data?.order,
    canCheckout =
      order &&
      ["PENDING", "READY"].includes(order.status) &&
      !["CANCEL_REQUESTED", "CANCELLED"].includes(order.subscriptionStatus);
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
  async function monthly(cancel: boolean) {
    if (!order || busy || (cancel && !confirmed)) return;
    setBusy(true);
    setActionError("");
    try {
      await session.api(
        `/api/my-giving/payments/${order.id}/subscription`,
        "POST",
        { expectedVersion: order.version, cancel },
      );
      setCancelOpen(false);
      await reload();
    } catch (e) {
      setCancelOpen(false);
      await reload();
      setActionError(
        e instanceof Error ? e.message : "Unable to check monthly giving.",
      );
    } finally {
      setBusy(false);
      setConfirmed(false);
    }
  }
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <Button
        title="Back to giving"
        subtle
        onPress={() => {
          if (router.canGoBack()) router.back();
          else router.replace("/giving");
        }}
      />
      <Text style={ui.eyebrow}>YOUR ONLINE GIFT</Text>
      <Message text={error} error />
      <Message text={actionError} error />
      {order && (
        <>
          <View style={ui.card}>
            <Text style={ui.title}>{money(order.amountMinor)}</Text>
            <Text style={ui.body}>
              {order.provider === "payfast" ? "Payfast" : "Yoco"} ·{" "}
              {order.recurring ? "Monthly giving" : "One-off gift"}
            </Text>
            <Text style={[ui.badge, { alignSelf: "flex-start" }]}>
              {label(order.status)}
            </Text>
            {order.mode === "test" && (
              <Message text="Test payment · No real contribution will be recorded." />
            )}
            {order.recurring && (
              <Text style={ui.body}>
                Monthly arrangement: {label(order.subscriptionStatus)}
              </Text>
            )}
            {order.subscriptionStatus === "CANCEL_REQUESTED" && (
              <Message text="Cancellation is requested and awaits provider confirmation. A payment already in progress may still complete." />
            )}
            {canCheckout && (
              <>
                <Button
                  title="Open secure checkout"
                  busy={busy}
                  onPress={() => void checkout()}
                />
                <Text style={[ui.body, { fontSize: 12 }]}>
                  After payment, close the browser to return here. Refresh to
                  see the provider-verified outcome.
                </Text>
              </>
            )}
            {order.recurring && order.subscriptionStatus !== "CANCELLED" && (
              <>
                <Button
                  title="Check monthly status"
                  subtle
                  disabled={busy}
                  onPress={() => void monthly(false)}
                />
                <Button
                  title="Cancel future monthly gifts"
                  subtle
                  disabled={busy}
                  onPress={() => {
                    setConfirmed(false);
                    setCancelOpen(true);
                  }}
                />
              </>
            )}
          </View>
          {data.charges.map((c) => (
            <View key={c.id} style={ui.card}>
              <Text style={ui.h2}>{money(c.amountMinor)}</Text>
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
              <Text style={ui.h2}>Refund · {money(r.amountMinor)}</Text>
              <Text style={ui.body}>
                {date(r.refundedOn)} ·{" "}
                {r.applied ? "Recorded" : "Being recorded"}
              </Text>
            </View>
          ))}
        </>
      )}
      <Sheet
        title="Cancel monthly giving?"
        visible={cancelOpen}
        onClose={() => !busy && setCancelOpen(false)}
      >
        <Text style={ui.body}>
          This requests cancellation of future monthly payments. It does not
          refund earlier contributions. Cancellation is complete only when the
          provider confirms.
        </Text>
        <Check
          label="I want to cancel future monthly payments."
          checked={confirmed}
          onChange={() => setConfirmed(!confirmed)}
          disabled={busy}
        />
        <Button
          title="Request cancellation"
          danger
          busy={busy}
          disabled={!confirmed}
          onPress={() => void monthly(true)}
        />
      </Sheet>
    </Screen>
  );
}
