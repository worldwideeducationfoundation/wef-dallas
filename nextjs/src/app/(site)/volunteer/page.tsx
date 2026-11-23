/** Port of public/volunteer.php */
import type { Metadata } from 'next';
import ContactFormPage from '@/components/site/ContactFormPage';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';

const SEO = { title: 'Volunteer — Worldwide Education Foundation', description: 'Volunteer with Worldwide Education Foundation.' };

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function VolunteerPage({ searchParams }: { searchParams: Promise<{ form?: string }> }) {
  await requirePageEnabled('volunteer');
  const { form } = await searchParams;
  return (
    <ContactFormPage
      formType="volunteer"
      seo={SEO}
      formCode={typeof form === 'string' ? form : undefined}
      subjectLabel="Interest"
      subjectPlaceholder={false}
      messageLabel="Tell us about your background"
      messagePlaceholder="How would you like to help?"
      submitLabel="Submit Application →"
    />
  );
}
