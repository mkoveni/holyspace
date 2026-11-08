import { useEffect, useState } from "react";
import { Pressable, Text, TextInput, View } from "react-native";
import { router } from "expo-router";
import { randomUUID } from "expo-crypto";
import { Ionicons } from "@expo/vector-icons";
import type { GiftIntent, Giving } from "@/core/models";
import { useSession } from "@/core/session";
import { ApiError } from "@/core/http";
import { date, label, money, zarMinor } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import {
  Badge,
  Button,
  Check,
  Choice,
  EmptyState,
  IconTile,
  Message,
  InfoLink,
  Screen,
  SectionHeader,
} from "@/ui/components";
import type { Tone } from "@/ui/components";
import { colors, fonts, ui } from "@/ui/theme";
import { SkeletonBlock } from "@/ui/skeleton";
const PRESETS = [100, 250, 500, 1000] as const;
function statusTone(status: string): Tone {
  if (status === "PAID" || status === "ACTIVE") return "success";
  if (status === "FAILED" || status === "CANCELLED") return "danger";
  if (status === "LOCKED" || status === "PAUSED") return "warning";
  return "gold";
}
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
  const chosenFund = data?.funds.find((f) => f.id === fund);
  const minor = zarMinor(amount);
  const locked = busy || Boolean(pending);
  function changeAmount(value: string) {
    // Accept the South African decimal comma as well as a point.
    setAmount(value.replace(",", "."));
    setReviewed(false);
  }
  const hero = (
    <>
      <View style={{ gap: 6 }}>
        <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
          GENEROSITY WITH PURPOSE
        </Text>
        <Text
          style={[
            ui.title,
            { color: colors.onDark, fontSize: 30, lineHeight: 36 },
          ]}
        >
          Give a little. Change a lot.
        </Text>
      </View>
      {data?.donor && (
        <View
          style={{
            backgroundColor: colors.heroCard,
            borderRadius: 24,
            borderWidth: 1,
            borderColor: colors.heroLine,
            paddingVertical: 22,
            paddingHorizontal: 18,
            alignItems: "center",
            gap: 6,
          }}
        >
          <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
            {chosenFund ? chosenFund.name.toUpperCase() : "CHOOSE A FUND"}
          </Text>
          <View
            style={{
              flexDirection: "row",
              alignItems: "baseline",
              justifyContent: "center",
              gap: 6,
            }}
          >
            <Text
              style={{
                fontFamily: fonts.display,
                fontSize: 30,
                color: colors.goldBright,
              }}
            >
              R
            </Text>
            <TextInput
              accessibilityLabel="Amount (ZAR)"
              value={amount}
              onChangeText={changeAmount}
              placeholder="0.00"
              placeholderTextColor={colors.onDarkMuted}
              keyboardType="decimal-pad"
              editable={!locked}
              selectionColor={colors.goldBright}
              style={{
                fontFamily: fonts.display,
                fontSize: 48,
                lineHeight: 58,
                letterSpacing: -1,
                color: colors.onDark,
                minWidth: 120,
                maxWidth: 240,
                textAlign: "center",
                paddingVertical: 0,
                borderBottomWidth: 1.5,
                borderColor: locked ? "transparent" : colors.heroLine,
              }}
            />
          </View>
          {amount !== "" && minor === null && (
            <Text style={[ui.caption, { color: colors.goldBright }]}>
              Enter R5 to R1 000 000, with up to two decimals.
            </Text>
          )}
          <Text
            style={[
              ui.caption,
              { color: colors.onDarkMuted, textAlign: "center" },
            ]}
          >
            {monthly ? "Every month, starting today" : "One-off gift"}
          </Text>
        </View>
      )}
    </>
  );
  return (
    <Screen hero={hero} refreshing={loading} onRefresh={() => void reload()}>
      <Message text={error} error />
      <Message text={actionError} error />
      {!data && !error && <SkeletonBlock />}
      {data && !data.donor ? (
        <View style={[ui.card, { alignItems: "flex-start" }]}>
          <IconTile icon="lock-closed-outline" tone="gold" />
          <Text style={ui.h2}>Let’s connect your giving.</Text>
          <Text style={ui.body}>
            Ask the church office to verify your donor access. Member profile
            access does not automatically open financial records.
          </Text>
        </View>
      ) : (
        data?.donor && (
          <>
            {!data.funds.length && (
              <Message text="No funds are available for online giving yet. Ask the church office to enable a fund for online giving, then refresh this page." />
            )}
            {!data.providers.length && (
              <Message text="No payment provider is available yet. Please contact the church office." />
            )}
            <View style={{ flexDirection: "row", gap: 8 }}>
              {PRESETS.map((preset) => {
                const value = `${preset}.00`;
                const active = amount === value;
                return (
                  <Pressable
                    key={preset}
                    accessibilityRole="button"
                    accessibilityLabel={`Give R${preset}`}
                    accessibilityState={{ selected: active, disabled: locked }}
                    disabled={locked}
                    onPress={() => changeAmount(value)}
                    style={({ pressed }) => ({
                      flex: 1,
                      minHeight: 48,
                      borderRadius: 16,
                      alignItems: "center",
                      justifyContent: "center",
                      backgroundColor: active ? colors.green : colors.card,
                      borderWidth: 1,
                      borderColor: active ? colors.green : colors.line,
                      opacity: locked ? 0.5 : 1,
                      transform: [{ scale: pressed ? 0.97 : 1 }],
                    })}
                  >
                    <Text
                      style={{
                        fontSize: 15,
                        fontWeight: "600",
                        color: active ? colors.onPrimary : colors.text,
                        fontFamily: fonts.sans,
                      }}
                    >
                      R {preset.toLocaleString("en-ZA")}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <View style={ui.card}>
              <Choice
                label="Give towards"
                emptyMessage="No funds available"
                value={fund}
                options={data.funds.map((f) => ({
                  value: f.id,
                  label: f.name,
                }))}
                onChange={(id) => {
                  setFund(id);
                  setReviewed(false);
                }}
                disabled={locked}
              />
              {Boolean(chosenFund?.description) && (
                <Text style={[ui.body, { fontSize: 14, marginTop: -6 }]}>
                  {chosenFund?.description}
                </Text>
              )}
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
                disabled={locked}
              />
              {selected?.recurring && (
                <Check
                  label="Give this amount every month"
                  checked={monthly}
                  onChange={() => {
                    setMonthly(!monthly);
                    setReviewed(false);
                  }}
                  disabled={locked}
                />
              )}
              {selected?.mode === "test" && (
                <Message text="Test mode · This is a simulation, not a real contribution." />
              )}
              <View style={ui.separator} />
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
                variant="gold"
                onPress={() => void give()}
                busy={busy}
                disabled={!canGive}
              />
              <View
                style={{
                  flexDirection: "row",
                  gap: 8,
                  alignItems: "flex-start",
                  justifyContent: "center",
                }}
              >
                <Ionicons
                  name="lock-closed"
                  size={13}
                  color={colors.muted}
                  style={{ marginTop: 2 }}
                />
                <Text
                  style={[ui.caption, { flexShrink: 1, textAlign: "center" }]}
                >
                  {monthly
                    ? "Payfast will collect today’s gift and future monthly gifts. You can request cancellation in your payment history."
                    : "Your card details are entered only with your payment provider."}
                </Text>
              </View>
            </View>

            <SectionHeader title="Your online gifts" />
            <Text style={[ui.body, { fontSize: 14, marginTop: -8 }]}>
              Follow payment progress and manage monthly giving.
            </Text>
            {!data.payments.items.length ? (
              <EmptyState
                icon="receipt-outline"
                text="Your online gifts will appear here."
              />
            ) : (
              <View style={ui.group}>
                {data.payments.items.map((p, i) => (
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
                    style={({ pressed }) => [
                      ui.row,
                      {
                        padding: 16,
                        backgroundColor: pressed
                          ? colors.sunken
                          : "transparent",
                        borderBottomWidth:
                          i === data.payments.items.length - 1 ? 0 : 1,
                        borderColor: colors.line,
                      },
                    ]}
                  >
                    <IconTile
                      size={40}
                      icon={p.recurring ? "repeat" : "heart-outline"}
                      tone={statusTone(p.status)}
                    />
                    <View style={{ flex: 1, gap: 4 }}>
                      <Text style={ui.h3}>{money(p.amountMinor)}</Text>
                      <Text style={ui.caption}>
                        {p.recurring ? "Monthly" : "One-off"} ·{" "}
                        {date(p.createdAt)}
                      </Text>
                      <Badge
                        tone={statusTone(p.status)}
                        text={`${p.mode === "test" ? "Test · " : ""}${label(p.status)}`}
                      />
                    </View>
                    <Ionicons
                      name="chevron-forward"
                      size={17}
                      color={colors.faint}
                    />
                  </Pressable>
                ))}
              </View>
            )}
            {(paymentOffset > 0 ||
              paymentOffset + 25 < data.payments.total) && (
              <View style={[ui.row, { justifyContent: "center" }]}>
                <Button
                  title="Previous payments"
                  variant="ghost"
                  disabled={!paymentOffset || loading}
                  onPress={() =>
                    setPaymentOffset(Math.max(0, paymentOffset - 25))
                  }
                />
                <Button
                  title="More payments"
                  variant="ghost"
                  disabled={
                    paymentOffset + 25 >= data.payments.total || loading
                  }
                  onPress={() => setPaymentOffset(paymentOffset + 25)}
                />
              </View>
            )}

            <SectionHeader title="Your contributions" />
            <InfoLink
              title="What’s included"
              sheetTitle="Your contributions"
              paragraphs={[
                "Recorded gifts, including those made through the church office.",
                "Test and pending payments are excluded.",
              ]}
            />
            {!data.gifts.length ? (
              <EmptyState
                icon="document-text-outline"
                text="Your recorded contributions will appear here."
              />
            ) : (
              <View style={ui.group}>
                {data.gifts.map((g, i) => (
                  <View
                    key={g.id}
                    style={[
                      ui.row,
                      {
                        padding: 16,
                        borderBottomWidth: i === data.gifts.length - 1 ? 0 : 1,
                        borderColor: colors.line,
                      },
                    ]}
                  >
                    <View style={{ flex: 1, gap: 4 }}>
                      <Text style={ui.h3}>
                        {money(g.amountMinor, g.currency)}
                      </Text>
                      <Text style={ui.caption}>
                        {date(g.receivedOn)} ·{" "}
                        {g.method === "ONLINE" ? "Online" : "Church office"}
                      </Text>
                    </View>
                    <Badge
                      tone={
                        g.reversed
                          ? "danger"
                          : g.refundedMinor
                            ? "warning"
                            : "success"
                      }
                      text={
                        g.reversed
                          ? "Reversed"
                          : g.refundedMinor
                            ? `${money(g.refundedMinor, g.currency)} refunded`
                            : "Recorded"
                      }
                    />
                  </View>
                ))}
              </View>
            )}
            {(giftOffset > 0 || giftOffset + 25 < data.giftTotal) && (
              <View style={[ui.row, { justifyContent: "center" }]}>
                <Button
                  title="Previous gifts"
                  variant="ghost"
                  disabled={!giftOffset || loading}
                  onPress={() => setGiftOffset(Math.max(0, giftOffset - 25))}
                />
                <Button
                  title="More gifts"
                  variant="ghost"
                  disabled={giftOffset + 25 >= data.giftTotal || loading}
                  onPress={() => setGiftOffset(giftOffset + 25)}
                />
              </View>
            )}
          </>
        )
      )}
    </Screen>
  );
}
