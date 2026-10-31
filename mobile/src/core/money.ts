export function zarMinor(value: string): number | null {
  const text = value.trim();
  if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(text)) return null;
  const [whole, fraction = ""] = text.split(".");
  const amount = Number(whole) * 100 + Number(fraction.padEnd(2, "0"));
  return amount >= 500 && amount <= 100_000_000 ? amount : null;
}
export function money(value: number, currency = "ZAR"): string {
  return new Intl.NumberFormat("en-ZA", { style: "currency", currency }).format(
    value / 100,
  );
}
export function date(value: string): string {
  return new Date(
    value.length === 10 ? `${value}T12:00:00` : value,
  ).toLocaleDateString("en-ZA", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}
export function label(value: string): string {
  return (
    (
      {
        PENDING: "Awaiting confirmation",
        READY: "Ready for payment",
        PAID: "Payment received",
        FAILED: "Payment unsuccessful",
        ACTIVE: "Active",
        PAUSED: "Paused",
        LOCKED: "Needs attention",
        CANCEL_REQUESTED: "Cancellation requested",
        CANCELLED: "Cancelled",
        POSTED: "Contribution recorded",
        POSTING_PENDING: "Contribution being recorded",
        SIMULATION: "Test payment · no real contribution",
        QUEUED: "Waiting to send",
        SENT: "Submitted to mail server",
        DELIVERED: "Delivered",
        NOT_ISSUED: "Not issued",
        BLOCKED: "Awaiting staff review",
        UNKNOWN: "Outcome uncertain",
      } as Record<string, string>
    )[value] || value.replaceAll("_", " ").toLowerCase()
  );
}
