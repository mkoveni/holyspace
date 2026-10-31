export interface FormField { key: string, label: string, type: 'text' | 'textarea' | 'email' | 'select' | 'checkbox', required: boolean, options?: string[] }
export interface ChurchForm { id: string, title: string, description: string, fields: FormField[], published: boolean, publicToken: string, version: number, eventId: string | null, occurrenceId: string | null }
export interface FormSubmission { id: string, formId: string, formVersion: number, snapshot: { title: string, fields: FormField[], eventId: string | null, occurrenceId: string | null }, answers: Record<string, string | boolean>, status: 'pending' | 'approved' | 'rejected', version: number, createdAt: string, personId: string | null, registrationId: string | null, reviewNote: string | null }
export interface PublicFormEvent { title: string, description: string, startsAt: string, endsAt: string, location: string, timezone: string, status: string }
export interface PublicForm { title: string, description: string, fields: FormField[], version: number, challenge: string, isEventRegistration?: boolean, event?: PublicFormEvent | null }
export interface FormsPage<T> { items: T[], nextAfter: string | null }
