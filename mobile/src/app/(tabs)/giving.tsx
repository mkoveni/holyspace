import { useEffect, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { router } from "expo-router";
import { randomUUID } from "expo-crypto";
import { Ionicons } from "@expo/vector-icons";
import type { GiftIntent, Giving } from "@/core/models";
import { useSession } from "@/core/session";
import { ApiError } from "@/core/http";
import { date, label, money, zarMinor } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import { Button, Check, Choice, Field, Message, Screen } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
export default function GivingScreen() {
  const session = useSession(),
    [paymentOffset, setPaymentOffset] = useState(0),
    [giftOffset, setGiftOffset] = useState(0);
  const { data, loading, error, reload } = useLoad(
    () =>
      session.api<Giving>(
        `/api/my-giving?limit=25&paymentOffset=${paymentOffset}&giftOffset=${giftOffset}`,
      ),
    [paymentOffset, giftOffset],
  );
  const [fund, setFund] = useState(""),
    [amount, setAmount] = useState(""),
    [provider, setProvider] = useState(""),
    [monthly, setMonthly] = useState(false),
    [reviewed, setReviewed] = useState(false),
    [pending, setPending] = useState<GiftIntent | null>(null),
    [busy, setBusy] = useState(false),
    [actionError, setActionError] = useState("");
  const effectiveProvider = provider || data?.providers[0]?.id || "";
  const selected = data?.providers.find((p) => p.id === effectiveProvider),
    canGive = Boolean(
      data?.donor &&
      data.funds.some((f) => f.id === fund) &&
      selected &&
      reviewed &&
      zarMinor(amount) !== null,
    );
  const { pending: loadPending } = session;
  useEffect(() => {
    let live = true;
    loadPending()
      .then((saved) => {
        if (live && saved) {
          setPending(saved);
          setFund(saved.fundId);
          setAmount((saved.amountMinor / 100).toFixed(2));
          setProvider(saved.provider);
          setMonthly(saved.recurring);
        }
      })
      .catch(() => {
        if (live)
          setActionError(
            "Unable to restore your previous gift request. Check payment activity before starting another.",
          );
      });
    return () => {
      live = false;
    };
  }, [loadPending, session.account?.id]);
  async function give() {
    if (!canGive || busy) return;
    setBusy(true);
    setActionError("");
    const intent = pending || {
      requestId: randomUUID(),
      fundId: fund,
      amountMinor: zarMinor(amount)!,
      provider: effectiveProvider,
      recurring: monthly,
    };
    setPending(intent);
    try {
      await session.savePending(intent);
      const result = await session.api<{ id: string }>(
        "/api/my-giving/payments",
        "POST",
        intent,
      );
      await session.savePending(null);
      setPending(null);
      setReviewed(false);
      setAmount("");
      router.push({ pathname: "/payment/[id]", params: { id: result.id } });
    } catch (e) {
      if (e instanceof ApiError && e.status < 500) {
        await session.savePending(null);
        setPending(null);
      }
      setActionError(
        (e instanceof Error ? e.message : "Unable to prepare your gift.") +
          " Check payment activity before creating another gift. Retry the same request if the outcome is uncertain.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <View style={{ gap: 10 }}>
        <Text style={ui.eyebrow}>GENEROSITY WITH PURPOSE</Text>
        <Text style={ui.title}>Give a little. Change a lot.</Text>
        <Text style={ui.body}>Your generosity makes room for good things.</Text>
      </View>
      <Message text={error} error />
      <Message text={actionError} error />
      {data && !data.donor ? (
        <View style={ui.card}>
          <Ionicons name="lock-closed-outline" size={30} color={colors.green} />
          <Text style={ui.h2}>Let’s connect your giving.</Text>
          <Text style={ui.body}>
            Ask the church office to verify your donor access. Member profile
            access does not automatically open financial records.
          </Text>
        </View>
      ) : (
        data?.donor && (
          <>
            <View style={ui.card}>
              <Text style={ui.eyebrow}>MAKE A CONTRIBUTION</Text>
              <Text style={ui.h2}>Every gift matters.</Text>
              {!data.funds.length && (
                <Message text="No funds are available for online giving yet. Ask the church office to enable a fund for online giving, then refresh this page." />
              )}
              {!data.providers.length && (
                <Message text="No payment provider is available yet. Please contact the church office." />
              )}
              <Choice
                label="Give towards"
                emptyMessage="No funds available"
                value={fund}
                options={data.funds.map((f) => ({
                  value: f.id,
                  label: f.name,
                }))}
                onChange={setFund}
                disabled={busy || Boolean(pending)}
              />
              {data.funds.find((f) => f.id === fund)?.description && (
                <Text style={ui.body}>
                  {data.funds.find((f) => f.id === fund)?.description}
                </Text>
              )}
              <Field
                label="Amount (ZAR)"
                value={amount}
                onChangeText={setAmount}
                placeholder="100.00"
                keyboardType="decimal-pad"
                editable={!busy && !pending}
              />
              <Choice
                label="Payment provider"
                emptyMessage="No payment providers available"
                value={effectiveProvider}
                options={data.providers.map((p) => ({
                  value: p.id,
                  label: p.name,
                }))}
                onChange={(id) => {
                  setProvider(id);
                  if (!data.providers.find((p) => p.id === id)?.recurring)
                    setMonthly(false);
                }}
                disabled={busy || Boolean(pending)}
              />
              {selected?.recurring && (
                <Check
                  label="Give this amount every month"
                  checked={monthly}
                  onChange={() => setMonthly(!monthly)}
                  disabled={busy || Boolean(pending)}
                />
              )}
              <Text style={[ui.body, { fontSize: 12 }]}>
                {monthly
                  ? "Payfast will collect today’s gift and future monthly gifts. You can request cancellation in your payment history."
                  : "Your card details are entered only with your payment provider."}
              </Text>
              {selected?.mode === "test" && (
                <Message text="Test mode · This is a simulation, not a real contribution." />
              )}
              <Check
                label={
                  monthly
                    ? "I agree to today’s payment and future monthly payments."
                    : "I have checked the amount and selected fund."
                }
                checked={reviewed}
                onChange={() => setReviewed(!reviewed)}
                disabled={busy}
              />
              <Button
                title={pending ? "Retry same gift request" : "Review my gift"}
                onPress={() => void give()}
                busy={busy}
                disabled={!canGive}
              />
            </View>
            <View style={ui.card}>
              <Text style={ui.eyebrow}>PAYMENT ACTIVITY</Text>
              <Text style={ui.h2}>Your online gifts</Text>
              <Text style={ui.body}>
                Follow payment progress and manage monthly giving.
              </Text>
              {!data.payments.items.length && (
                <Text style={ui.body}>Your online gifts will appear here.</Text>
              )}
              {data.payments.items.map((p) => (
                <Pressable
                  key={p.id}
                  accessibilityRole="button"
                  accessibilityLabel={`View ${money(p.amountMinor)} ${p.recurring ? "monthly" : "one-off"} payment`}
                  onPress={() =>
                    router.push({
                      pathname: "/payment/[id]",
                      params: { id: p.id },
                    })
                  }
                  style={[
                    ui.row,
                    {
                      paddingVertical: 14,
                      borderBottomWidth: 1,
                      borderColor: colors.line,
                    },
                  ]}
                >
                  <View style={{ flex: 1, gap: 7 }}>
                    <Text
                      style={{
                        fontWeight: "600",
                        fontSize: 18,
                        color: colors.text,
                      }}
                    >
                      {money(p.amountMinor)}
                    </Text>
                    <Text style={{ fontSize: 12, color: colors.muted }}>
                      {p.recurring ? "Monthly" : "One-off"} ·{" "}
                      {date(p.createdAt)}
                    </Text>
                    <Text style={[ui.badge, { alignSelf: "flex-start" }]}>
                      {p.mode === "test" ? "Test · " : ""}
                      {label(p.status)}
                    </Text>
                  </View>
                  <Ionicons
                    name="chevron-forward"
                    size={18}
                    color={colors.muted}
                  />
                </Pressable>
              ))}
              <View style={ui.row}>
                <Button
                  title="Previous payments"
                  subtle
                  disabled={!paymentOffset || loading}
                  onPress={() =>
                    setPaymentOffset(Math.max(0, paymentOffset - 25))
                  }
                />
                <Button
                  title="More payments"
                  subtle
                  disabled={
                    paymentOffset + 25 >= data.payments.total || loading
                  }
                  onPress={() => setPaymentOffset(paymentOffset + 25)}
                />
              </View>
            </View>
            <View style={ui.card}>
              <Text style={ui.eyebrow}>GIVING HISTORY</Text>
              <Text style={ui.h2}>Your contributions</Text>
              <Text style={ui.body}>
                Recorded gifts, including those made through the church office.
                Test and pending payments are excluded.
              </Text>
              {!data.gifts.length && (
                <Text style={ui.body}>
                  Your recorded contributions will appear here.
                </Text>
              )}
              {data.gifts.map((g) => (
                <View
                  key={g.id}
                  style={[
                    ui.row,
                    {
                      paddingVertical: 15,
                      borderBottomWidth: 1,
                      borderColor: colors.line,
                      flexWrap: "wrap",
                    },
                  ]}
                >
                  <View style={{ gap: 6 }}>
                    <Text
                      style={{
                        fontSize: 18,
                        color: colors.text,
                        fontWeight: "600",
                      }}
                    >
                      {money(g.amountMinor, g.currency)}
                    </Text>
                    <Text style={{ fontSize: 12, color: colors.muted }}>
                      {date(g.receivedOn)} ·{" "}
                      {g.method === "ONLINE" ? "Online" : "Church office"}
                    </Text>
                  </View>
                  <Text style={ui.badge}>
                    {g.reversed
                      ? "Reversed"
                      : g.refundedMinor
                        ? `${money(g.refundedMinor, g.currency)} refunded`
                        : "Recorded"}
                  </Text>
                </View>
              ))}
              <View style={ui.row}>
                <Button
                  title="Previous gifts"
                  subtle
                  disabled={!giftOffset || loading}
                  onPress={() => setGiftOffset(Math.max(0, giftOffset - 25))}
                />
                <Button
                  title="More gifts"
                  subtle
                  disabled={giftOffset + 25 >= data.giftTotal || loading}
                  onPress={() => setGiftOffset(giftOffset + 25)}
                />
              </View>
            </View>
          </>
        )
      )}
    </Screen>
  );
}
