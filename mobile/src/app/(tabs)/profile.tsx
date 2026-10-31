import { useState } from "react";
import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import type { Contact, MemberProfile, Profile } from "@/core/models";
import { useSession } from "@/core/session";
import { date } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import { Button, Choice, Field, Message, Screen, Sheet } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
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
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <View style={{ gap: 10 }}>
        <Text style={ui.eyebrow}>MY PERSONAL DETAILS</Text>
        <Text style={ui.title}>A profile that feels like you.</Text>
        <Text style={ui.body}>Help your church stay connected with you.</Text>
      </View>
      <Message text={error} error />
      <Message text={success} />
      {!editing && !contactOpen && <Message text={actionError} error />}
      {data && !p && (
        <View style={ui.card}>
          <Ionicons
            name="person-circle-outline"
            color={colors.green}
            size={38}
          />
          <Text style={ui.h2}>Let’s connect your profile.</Text>
          <Text style={ui.body}>
            Ask your church office to verify your account and link it to your
            People record. Giving access is verified separately.
          </Text>
        </View>
      )}
      {p && (
        <>
          <View style={ui.card}>
            <View style={ui.row}>
              <View style={{ flex: 1, gap: 8 }}>
                <Text style={ui.h2}>
                  {p.details.firstName} {p.details.lastName}
                </Text>
                <Text style={ui.body}>
                  {p.membershipType.replaceAll("_", " ").toLowerCase()}
                </Text>
              </View>
              <Ionicons
                name="person-circle-outline"
                size={42}
                color={colors.green}
              />
            </View>
            <View style={ui.separator} />
            <Text style={ui.body}>
              Date of birth:{" "}
              {p.details.dateOfBirth
                ? date(p.details.dateOfBirth)
                : "Not recorded"}
            </Text>
            <Text style={[ui.body, { fontSize: 12 }]}>
              Please contact the church office to correct your birth date or
              membership status.
            </Text>
            <Button title="Edit my personal details" onPress={() => edit(p)} />
          </View>
          <View style={ui.card}>
            <Text style={ui.eyebrow}>CONTACT DETAILS</Text>
            <Text style={ui.h2}>Stay in the loop.</Text>
            {!p.contacts.length && (
              <Text style={ui.body}>
                Add your phone or email so your church can reach you.
              </Text>
            )}
            {p.contacts.map((c) => (
              <View
                key={c.id}
                style={{
                  gap: 10,
                  paddingVertical: 10,
                  borderBottomWidth: 1,
                  borderColor: colors.line,
                }}
              >
                <View style={ui.row}>
                  <Text style={[ui.body, { fontSize: 11 }]}>
                    {c.kind === "EMAIL" ? "Email" : "Phone"}
                    {c.primary ? " · Primary" : ""}
                  </Text>
                  <Ionicons
                    name={c.kind === "EMAIL" ? "mail-outline" : "call-outline"}
                    size={19}
                    color={colors.muted}
                  />
                </View>
                <Text style={{ fontSize: 15, color: colors.text }}>
                  {c.value}
                </Text>
                <View style={ui.row}>
                  <Button
                    title={`Edit ${c.kind === "EMAIL" ? "email" : "phone"}`}
                    subtle
                    onPress={() => editContact(c)}
                  />
                  <Button
                    title="Remove contact"
                    subtle
                    onPress={() => {
                      setActionError("");
                      setRemoving(c);
                    }}
                  />
                </View>
              </View>
            ))}
            <Button
              title="Add contact details"
              subtle
              onPress={() => editContact(null)}
            />
            <Text style={[ui.body, { fontSize: 12 }]}>
              Changing your contact email does not change the email used to sign
              in. Updates may require fresh verification for church
              communications.
            </Text>
          </View>
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
