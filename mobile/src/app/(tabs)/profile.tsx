import { useState } from "react";
import { Text, View } from "react-native";
import type { Contact, MemberProfile, Profile } from "@/core/models";
import { useSession } from "@/core/session";
import { date } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import {
  Avatar,
  Button,
  Choice,
  EmptyState,
  Field,
  IconTile,
  InfoRow,
  Message,
  NoticeCard,
  InfoLink,
  Screen,
  SectionHeader,
  Sheet,
} from "@/ui/components";
import { colors, fonts, ui } from "@/ui/theme";
import { SkeletonBlock } from "@/ui/skeleton";
export default function ProfileScreen() {
  const { api } = useSession(),
    { data, loading, error, reload } = useLoad(() =>
      api<MemberProfile>("/api/member/profile"),
    );
  const [editing, setEditing] = useState(false),
    [first, setFirst] = useState(""),
    [last, setLast] = useState(""),
    [title, setTitle] = useState(""),
    [gender, setGender] = useState("UNKNOWN"),
    [version, setVersion] = useState(0),
    [busy, setBusy] = useState(false),
    [actionError, setActionError] = useState(""),
    [success, setSuccess] = useState("");
  const [contactOpen, setContactOpen] = useState(false),
    [contact, setContact] = useState<Contact | null>(null),
    [kind, setKind] = useState("PHONE"),
    [value, setValue] = useState(""),
    [removing, setRemoving] = useState<Contact | null>(null);
  function edit(p: Profile) {
    setActionError("");
    setFirst(p.details.firstName);
    setLast(p.details.lastName);
    setTitle(p.details.titleCode || "");
    setGender(p.details.gender);
    setVersion(p.version);
    setEditing(true);
  }
  function editContact(c: Contact | null) {
    if (!data?.profile) return;
    setActionError("");
    setContact(c);
    setKind(c?.kind || "PHONE");
    setValue(c?.value || "");
    setVersion(data.profile.version);
    setContactOpen(true);
  }
  async function save() {
    if (busy) return;
    setBusy(true);
    setActionError("");
    try {
      await api("/api/member/profile", "PUT", {
        titleCode: title || null,
        firstName: first.trim(),
        lastName: last.trim(),
        gender,
        expectedVersion: version,
      });
      setEditing(false);
      setSuccess("Your personal details have been updated.");
      await reload();
    } catch (e) {
      setActionError(
        e instanceof Error ? e.message : "Unable to save your profile.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function saveContact() {
    if (busy) return;
    setBusy(true);
    setActionError("");
    try {
      await api("/api/member/profile/contacts", "PUT", {
        contactId: contact?.id || null,
        kind,
        value: value.trim(),
        expectedVersion: version,
      });
      setContactOpen(false);
      setSuccess("Your contact details have been updated.");
      await reload();
    } catch (e) {
      setActionError(
        e instanceof Error ? e.message : "Unable to save contact.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function remove() {
    if (!removing || !data?.profile || busy) return;
    setBusy(true);
    setActionError("");
    try {
      await api(
        `/api/member/profile/contacts/${removing.id}?expectedVersion=${data.profile.version}`,
        "DELETE",
      );
      setRemoving(null);
      setSuccess("Contact removed from your active details.");
      await reload();
    } catch (e) {
      setActionError(
        e instanceof Error ? e.message : "Unable to remove contact.",
      );
    } finally {
      setBusy(false);
    }
  }
  const p = data?.profile;
  return (
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      hero={
        p ? (
          <View style={{ alignItems: "center", gap: 10, paddingTop: 4 }}>
            <Avatar
              name={`${p.details.firstName} ${p.details.lastName}`}
              size={76}
            />
            <Text
              accessibilityRole="header"
              style={{
                fontFamily: fonts.display,
                fontSize: 28,
                lineHeight: 34,
                color: colors.onDark,
                textAlign: "center",
              }}
            >
              {p.details.firstName} {p.details.lastName}
            </Text>
            <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
              {p.membershipType.replaceAll("_", " ")}
            </Text>
          </View>
        ) : (
          <View style={{ gap: 6 }}>
            <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
              MY PERSONAL DETAILS
            </Text>
            <Text style={[ui.title, { color: colors.onDark }]}>
              A profile that feels like you.
            </Text>
          </View>
        )
      }
    >
      <Message text={error} error />
      <Message text={success} />
      {!data && !error && <SkeletonBlock />}
      {!editing && !contactOpen && <Message text={actionError} error />}
      {data && !p && (
        <NoticeCard
          icon="person-circle-outline"
          title="Let’s connect your profile."
          text="Ask your church office to verify your account and link it to your People record. Giving access is verified separately."
        />
      )}
      {p && (
        <>
          <SectionHeader title="Personal details" />
          <View style={ui.group}>
            <InfoRow
              icon="gift-outline"
              title={
                p.details.dateOfBirth
                  ? date(p.details.dateOfBirth)
                  : "Not recorded"
              }
              subtitle="Date of birth"
            />
            <InfoRow
              last
              icon="ribbon-outline"
              title={p.membershipType
                .replaceAll("_", " ")
                .toLowerCase()
                .replace(/^./, (c) => c.toUpperCase())}
              subtitle="Membership"
            />
          </View>
          <Text style={[ui.caption, { marginTop: -8 }]}>
            Please contact the church office to correct your birth date or
            membership status.
          </Text>
          <Button
            title="Edit my personal details"
            variant="secondary"
            icon="create-outline"
            onPress={() => edit(p)}
          />
          <SectionHeader title="Contact details" />
          {!p.contacts.length && (
            <EmptyState
              icon="call-outline"
              text="Add your phone or email so your church can reach you."
            />
          )}
          {p.contacts.length > 0 && (
            <View style={ui.group}>
              {p.contacts.map((c, i) => (
                <View
                  key={c.id}
                  style={{
                    gap: 10,
                    padding: 16,
                    borderBottomWidth: i === p.contacts.length - 1 ? 0 : 1,
                    borderColor: colors.line,
                  }}
                >
                  <View style={[ui.row, { justifyContent: "flex-start" }]}>
                    <IconTile
                      size={38}
                      icon={
                        c.kind === "EMAIL" ? "mail-outline" : "call-outline"
                      }
                    />
                    <View style={{ flex: 1, gap: 2 }}>
                      <Text style={[ui.h3, { fontSize: 15 }]}>{c.value}</Text>
                      <Text style={ui.caption}>
                        {c.kind === "EMAIL" ? "Email" : "Phone"}
                        {c.primary ? " · Primary" : ""}
                      </Text>
                    </View>
                  </View>
                  <View style={{ flexDirection: "row", gap: 4 }}>
                    <Button
                      title={`Edit ${c.kind === "EMAIL" ? "email" : "phone"}`}
                      variant="ghost"
                      style={{
                        minHeight: 40,
                        paddingVertical: 8,
                        paddingHorizontal: 12,
                      }}
                      onPress={() => editContact(c)}
                    />
                    <Button
                      title="Remove contact"
                      variant="ghost"
                      style={{
                        minHeight: 40,
                        paddingVertical: 8,
                        paddingHorizontal: 12,
                      }}
                      onPress={() => {
                        setActionError("");
                        setRemoving(c);
                      }}
                    />
                  </View>
                </View>
              ))}
            </View>
          )}
          <Button
            title="Add contact details"
            variant="secondary"
            icon="add"
            onPress={() => editContact(null)}
          />
          <InfoLink
            sheetTitle="About contact details"
            paragraphs={[
              "Changing your contact email does not change the email used to sign in.",
              "Updates may require fresh verification for church communications.",
            ]}
          />
        </>
      )}
      <Sheet
        title="Edit personal details"
        visible={editing}
        onClose={() => !busy && setEditing(false)}
      >
        <Message text={actionError} error />
        <Choice
          label="Title"
          value={title}
          options={[
            { value: "", label: "No title" },
            ...(data?.titles || []).map((t) => ({
              value: t.code,
              label: t.label,
            })),
          ]}
          onChange={setTitle}
          disabled={busy}
        />
        <Field
          label="First name"
          value={first}
          onChangeText={setFirst}
          editable={!busy}
        />
        <Field
          label="Last name"
          value={last}
          onChangeText={setLast}
          editable={!busy}
        />
        <Choice
          label="Gender"
          value={gender}
          options={[
            { value: "UNKNOWN", label: "Unspecified" },
            { value: "FEMALE", label: "Female" },
            { value: "MALE", label: "Male" },
          ]}
          onChange={setGender}
          disabled={busy}
        />
        <Button
          title="Save personal details"
          busy={busy}
          disabled={!first.trim() || !last.trim()}
          onPress={() => void save()}
        />
      </Sheet>
      <Sheet
        title={contact ? "Update contact" : "Add contact details"}
        visible={contactOpen}
        onClose={() => !busy && setContactOpen(false)}
      >
        <Message text={actionError} error />
        <Choice
          label="Contact type"
          value={kind}
          options={[
            { value: "PHONE", label: "Phone" },
            { value: "EMAIL", label: "Email" },
          ]}
          onChange={setKind}
          disabled={busy || Boolean(contact)}
        />
        <Field
          label={kind === "EMAIL" ? "Email address" : "Phone number"}
          value={value}
          onChangeText={setValue}
          autoCapitalize="none"
          keyboardType={kind === "EMAIL" ? "email-address" : "phone-pad"}
          editable={!busy}
        />
        <Text style={ui.body}>
          This becomes your primary contact of this type. Previous details
          remain in your church’s history.
        </Text>
        <Button
          title="Save contact details"
          busy={busy}
          disabled={!value.trim()}
          onPress={() => void saveContact()}
        />
      </Sheet>
      <Sheet
        title="Remove contact details?"
        visible={Boolean(removing)}
        onClose={() => !busy && setRemoving(null)}
      >
        <Message text={actionError} error />
        <Text style={ui.body}>
          {removing?.value} will be removed from your active contact details.
          The church keeps its history.
        </Text>
        <Button
          title="Confirm contact removal"
          danger
          busy={busy}
          onPress={() => void remove()}
        />
      </Sheet>
    </Screen>
  );
}
