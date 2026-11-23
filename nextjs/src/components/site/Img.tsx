/** Port of imageTag() in includes/assets.php: width/height, srcset from -480w.. variants, lazy/priority. */
import { imageSize, imageSrcset } from '@/lib/assets';

type Props = {
  src: string;
  alt: string;
  priority?: boolean;
  lazy?: boolean;
  defer?: boolean;
  width?: number;
  height?: number;
  sizes?: string;
  className?: string;
  attrs?: Record<string, string>;
};

const BLANK = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

export default async function Img({ src, alt, priority = false, lazy = true, defer = false, width, height, sizes = '100vw', className, attrs = {} }: Props) {
  src = src.replace(/^\/+/, '');
  const isLazy = priority ? false : lazy;
  if (width === undefined || height === undefined) {
    const size = await imageSize(src);
    if (size) {
      width ??= size.width;
      height ??= size.height;
    }
  }
  const srcset = await imageSrcset(src);
  const props: Record<string, unknown> = {};
  if (defer) {
    props.src = BLANK;
    props['data-src'] = src;
    if (srcset) {
      props['data-srcset'] = srcset;
      props['data-sizes'] = sizes;
    }
  } else {
    props.src = src;
    if (srcset) {
      props.srcSet = srcset;
      props.sizes = sizes;
    }
  }
  if (width !== undefined && height !== undefined) {
    props.width = Math.trunc(width);
    props.height = Math.trunc(height);
  }
  props.alt = alt;
  if (alt === '') props['aria-hidden'] = 'true';
  if (className) props.className = className;
  props.loading = isLazy ? 'lazy' : 'eager';
  props.decoding = priority ? 'sync' : 'async';
  if (priority) props.fetchPriority = 'high';
  return <img {...props} {...attrs} />;
}
