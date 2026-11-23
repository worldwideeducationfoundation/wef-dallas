/** The programme card from active-projects.php (uiverse-cards.css). */
import { uploadUrl } from '@/lib/content';
import { phpTrim } from '@/lib/html';
import { css } from '@/lib/style';

const NBSP = ' ';

export default function ProgramCard({ program, index, hubParam = '' }: { program: Record<string, any>; index: number; hubParam?: string }) {
  const photo = uploadUrl(program.image_path);
  const delay = index === 0 ? null : String(Number((0.1 + (index - 1) * 0.05).toFixed(2)));
  const stats: { value: string; label: string }[] = [];
  for (let n = 1; n <= 3; n++) {
    const value = phpTrim(program[`stat${n}_value`]);
    const label = phpTrim(program[`stat${n}_label`]);
    if (value !== '' || label !== '') stats.push({ value, label });
  }
  while (stats.length < 3) stats.push({ value: NBSP, label: NBSP });

  return (
    <a
      href={String(program.slug) + hubParam}
      className="card uiverse-card reveal"
      style={delay === null ? undefined : css(`transition-delay: ${delay}s;`)}
      id={index === 0 ? 'project-eclc' : undefined}
    >
      <div className="top-section" style={photo !== null ? css(`background-image: url('${photo}');`) : undefined}>
        <div className="border"></div>
        <div className="icons">
          <div className="logo">
            <span className="card-year">{program.badge_text ? String(program.badge_text) : NBSP}</span>
          </div>
          <div className="social-media">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M5 12h14M12 5l7 7-7 7" />
            </svg>
          </div>
        </div>
      </div>
      <div className="bottom-section">
        <span className="title">{program.name}</span>
        <p className="card-desc">{program.summary}</p>
        <div className="row row1">
          {stats.map((s, i) => (
            <div className="item" key={i}>
              <span className="regular-text">{s.label}</span>
              <span className="big-text">{s.value}</span>
            </div>
          ))}
        </div>
      </div>
    </a>
  );
}
