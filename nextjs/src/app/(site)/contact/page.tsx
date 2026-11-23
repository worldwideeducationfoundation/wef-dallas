/** Port of public/contact.php */
import type { Metadata } from 'next';
import ContactFormPage from '@/components/site/ContactFormPage';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';

const SEO = { title: 'Contact Us — Worldwide Education Foundation', description: 'Get in touch with Worldwide Education Foundation.' };

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function ContactPage({ searchParams }: { searchParams: Promise<{ form?: string }> }) {
  await requirePageEnabled('contact');
  const { form } = await searchParams;
  return (
    <ContactFormPage
      formType="contact"
      seo={SEO}
      formCode={typeof form === 'string' ? form : undefined}
      subjectLabel="Subject"
      subjectPlaceholder
      messageLabel="Message"
      messagePlaceholder="Tell us how we can help..."
      submitLabel="Send Message →"
    />
  );
}
