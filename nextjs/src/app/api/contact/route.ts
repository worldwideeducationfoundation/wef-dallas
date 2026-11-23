/**
 * Contact / volunteer / newsletter form endpoint (port of public/api/contact.php).
 * Also reachable as /api/contact.php (see next.config.ts rewrites), which is
 * the URL the forms post to.
 *
 * JSON in/out when the browser asks for JSON (js/main.js does); otherwise a
 * 303 back to the form page with ?form=<code>#contact-form, so it works with
 * JavaScript off.
 */
import { NextResponse } from 'next/server';
import {
  SUBMISSION_FORMS,
  submissionFloodLimitReached,
  submissionForm,
  submissionInsert,
  submissionSuccessMessage,
  submissionValidate,
} from '@/lib/forms';
import { publicLog } from '@/lib/log';
import { ccSyncSubmission } from '@/lib/constant-contact';
import { phpTrim } from '@/lib/html';

function clientIp(request: Request): string {
  return (request.headers.get('x-forwarded-for') ?? '').split(',')[0].trim() || request.headers.get('x-real-ip') || '0.0.0.0';
}

function respond(request: Request, status: number, code: string, message: string, formType = 'contact', errors: Record<string, string> = {}) {
  const wantsJson = (request.headers.get('accept') ?? '').toLowerCase().includes('application/json');
  if (wantsJson) {
    return NextResponse.json({ ok: code === 'ok', code, message, errors }, { status, headers: { 'Cache-Control': 'no-store' } });
  }
  const page = (submissionForm(formType)?.page ?? SUBMISSION_FORMS.contact.page).replace(/\.php$/, '').replace(/^index$/, '');
  const location = `/${page}?form=${encodeURIComponent(code)}#contact-form`;
  return new NextResponse(null, { status: 303, headers: { Location: location } });
}

export async function GET(request: Request) {
  const res = respond(request, 405, 'method', 'This endpoint only accepts form submissions.');
  res.headers.set('Allow', 'POST');
  return res;
}

export async function POST(request: Request) {
  let input: Record<string, string> = {};
  try {
    const type = request.headers.get('content-type') ?? '';
    if (type.includes('application/json')) {
      input = (await request.json()) as Record<string, string>;
    } else {
      const form = await request.formData();
      for (const [k, v] of form.entries()) if (typeof v === 'string') input[k] = v;
    }
  } catch {
    input = {};
  }
  const ip = clientIp(request);
  const formType = String(input.form_type ?? 'contact');

  if (phpTrim(input.website ?? '') !== '') {
    publicLog('INFO', `form honeypot triggered from ip=${ip}`);
    return respond(request, 200, 'ok', 'Thank you — your message has been received.', formType);
  }

  try {
    if (await submissionFloodLimitReached(ip)) {
      publicLog('WARN', `form flood limit hit from ip=${ip}`);
      return respond(request, 429, 'throttled', 'You have sent several messages already. Please wait a few minutes before sending another.', formType);
    }

    const check = submissionValidate(input);
    if (Object.keys(check.errors).length || !check.values) {
      return respond(request, 422, 'invalid', 'Please check the highlighted fields and try again.', formType, check.errors);
    }

    const id = await submissionInsert(check.values, ip, request.headers.get('user-agent'));
    publicLog('INFO', `form submission #${id} stored (${check.values.form_type}) from ${check.values.email}`);

    if (check.values.form_type === 'newsletter') await ccSyncSubmission(id, check.values.email);

    return respond(request, 201, 'ok', submissionSuccessMessage(check.values.form_type), check.values.form_type);
  } catch (e) {
    publicLog('EXCEPTION', `contact form: ${(e as Error).message}`);
    return respond(request, 503, 'method', 'Something went wrong sending that. Please try again.', formType);
  }
}
