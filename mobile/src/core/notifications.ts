// Only known member destinations are accepted. Notification data never supplies a URL.
export function notificationDestination(
  destination: string,
): "/volunteering" | "/events" | null {
  if (destination === "VOLUNTEERING") return "/volunteering";
  if (destination === "EVENTS") return "/events";
  return null;
}
export function notificationTopic(topic: string): string {
  return topic === "VOLUNTEERING"
    ? "Serving & volunteering"
    : topic === "EVENTS"
      ? "Events & bookings"
      : "Church updates";
}
export function unreadLabel(count: number): string {
  return count > 99 ? "99+ unread" : `${count} unread`;
}
