/** Contact / volunteer / newsletter submissions (port of includes/form-submissions.php). */
import 'server-only';
import { dbFetchColumn, dbInsert } from './db';
import { phpTrim } from './html';

type FormDef = { label: string; page: string; subjects: Record<string, string>; fields?: string[]; successMessage?: string };

export const SUBMISSION_FORMS: Record<string, FormDef> = {
  contact: {
    label: 'Contact',
    page: 'contact.php',
    subjects: {
      general: 'General Inquiry',
      partnership: 'Partnership Opportunity',
      volunteer: 'Volunteer with WEF',
      donation: 'Donation Question',
      media: 'Media & Press',
      other: 'Other',
    },
  },
  volunteer: {
    label: 'Volunteer',
    page: 'volunteer.php',
    subjects: { volunteer: 'Volunteer with WEF', mentorship: 'Mentorship', other: 'Other' },
  },
  newsletter: {
    label: 'Newsletter',
    page: 'index.php',
    fields: ['email'],
    successMessage: "You're subscribed — thank you for supporting WEF.",
    subjects: { newsletter: 'Newsletter signup' },
  },
};

export const SUBMISSION_STATUSES: Record<string, string> = { new: 'New', read: 'Read', replied: 'Replied', archived: 'Archived' };

export const SUBMISSION_RESULT_MESSAGES: Record<string, { type: string; text: string }> = {
  ok: { type: 'success', text: 'Thank you — your message has been received. We will get back to you shortly.' },
  invalid: { type: 'error', text: 'Some details were missing or incorrect. Please check the form and send it again.' },
  throttled: { type: 'error', text: 'You have sent several messages already. Please wait a few minutes before sending another.' },
  method: { type: 'error', text: 'Something went wrong sending that. Please try again.' },
};

export const SUBMISSION_MAX_NAME = 160;
export const SUBMISSION_MAX_EMAIL = 190;
export const SUBMISSION_MAX_MESSAGE = 5000;
export const SUBMISSION_MAX_PER_IP = 5;
export const SUBMISSION_FLOOD_WINDOW_MINUTES = 15;

export function submissionSuccessMessage(type: string): string {
  return SUBMISSION_FORMS[type]?.successMessage ?? 'Thank you — your message has been received. We will get back to you shortly.';
}
export function submissionFields(type: string): string[] {
  return SUBMISSION_FORMS[type]?.fields ?? ['name', 'email', 'subject', 'message'];
}
export function submissionResultMessage(code: string | null | undefined) {
  return code == null ? null : (SUBMISSION_RESULT_MESSAGES[code] ?? null);
}
export function submissionForm(type: string): FormDef | null {
  return SUBMISSION_FORMS[type] ?? null;
}
export function submissionSubjects(type: string): Record<string, string> {
  return SUBMISSION_FORMS[type]?.subjects ?? {};
}
export function submissionSubjectLabel(type: string, subject: string): string {
  return SUBMISSION_FORMS[type]?.subjects?.[subject] ?? subject;
}
export function submissionFormLabel(type: string): string {
  return SUBMISSION_FORMS[type]?.label ?? type;
}
export function submissionStatusLabel(status: string): string {
  return SUBMISSION_STATUSES[status] ?? status;
}

/** PHP FILTER_VALIDATE_EMAIL is stricter than most; this mirrors its common rules. */
export function isValidEmail(email: string): boolean {
  if (email.length > 254) return false;
  const m = /^([A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+)*)@((?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63})$/.exec(email);
  return m !== null && m[1].length <= 64;
}

const mbLen = (s: string) => [...s].length;

export function submissionValidate(input: Record<string, unknown>) {
  const errors: Record<string, string> = {};
  const type = phpTrim(input.form_type ?? '');
  if (!submissionForm(type)) {
    return { values: null, errors: { form_type: 'This form could not be identified. Please reload the page and try again.' } };
  }
  let name = phpTrim(String(input.name ?? '').replace(/\s+/gu, ' '));
  const email = phpTrim(input.email ?? '');
  let subject = phpTrim(input.subject ?? '');
  const message = phpTrim(input.message ?? '');
  const collects = submissionFields(type);

  if (collects.includes('name')) {
    if (name === '') errors.name = 'Please enter your name.';
    else if (mbLen(name) > SUBMISSION_MAX_NAME) errors.name = `Please keep your name under ${SUBMISSION_MAX_NAME} characters.`;
  }
  if (email === '') errors.email = 'Please enter your email address.';
  else if (mbLen(email) > SUBMISSION_MAX_EMAIL || !isValidEmail(email)) errors.email = 'Please enter a valid email address.';

  if (collects.includes('subject')) {
    if (subject === '') errors.subject = 'Please choose a subject.';
    else if (!(subject in SUBMISSION_FORMS[type].subjects)) errors.subject = 'Please choose one of the listed subjects.';
  } else {
    subject = Object.keys(SUBMISSION_FORMS[type].subjects)[0] ?? 'other';
  }
  if (collects.includes('message')) {
    if (message === '') errors.message = 'Please tell us how we can help.';
    else if (mbLen(message) > SUBMISSION_MAX_MESSAGE)
      errors.message = `Please keep your message under ${SUBMISSION_MAX_MESSAGE.toLocaleString('en-US')} characters.`;
  }
  if (!collects.includes('name') && name === '') name = '';
  return { values: { form_type: type, name, email, subject, message }, errors };
}

export async function submissionFloodLimitReached(ip: string): Promise<boolean> {
  const recent = Number(
    await dbFetchColumn(
      'SELECT COUNT(*) FROM form_submissions WHERE ip_address = :ip AND created_at > (NOW() - INTERVAL :minutes MINUTE)',
      { ip, minutes: SUBMISSION_FLOOD_WINDOW_MINUTES },
      0,
    ),
  );
  return recent >= SUBMISSION_MAX_PER_IP;
}

export async function submissionInsert(
  values: { form_type: string; name: string; email: string; subject: string; message: string },
  ip: string,
  userAgent: string | null,
): Promise<number> {
  return dbInsert(
    `INSERT INTO form_submissions (form_type, name, email, subject, message, ip_address, user_agent)
     VALUES (:form_type, :name, :email, :subject, :message, :ip, :agent)`,
    { ...values, ip, agent: userAgent === null ? null : [...userAgent].slice(0, 255).join('') },
  );
}

export async function submissionUnreadCount(): Promise<number> {
  return Number(await dbFetchColumn("SELECT COUNT(*) FROM form_submissions WHERE status = 'new' AND deleted_at IS NULL", [], 0));
}
