/**
 * Format fasilitas kampus yang dapat berformat string, array, atau objek JSONB (misal {ac: true, proyektor: true}).
 */
export const formatFasilitas = (fasilitas) => {
  if (!fasilitas) return '';
  if (typeof fasilitas === 'string') return fasilitas;
  if (Array.isArray(fasilitas)) return fasilitas.filter(Boolean).join(', ');
  if (typeof fasilitas === 'object') {
    return Object.entries(fasilitas)
      .filter(([_, v]) => Boolean(v))
      .map(([k, v]) => (typeof v === 'boolean' ? k.toUpperCase() : `${k.toUpperCase()} (${v})`))
      .join(', ');
  }
  return String(fasilitas);
};

/**
 * Pecah teks markdown ringan (**bold** dan _italic_) menjadi segmen aman untuk React.
 */
export const parseTeksRingkas = (text) => {
  if (!text || typeof text !== 'string') return [{ type: 'text', value: String(text ?? '') }];
  const tokens = [];
  const regex = /(\*\*[^*]+\*\*|_[^_]+_)/g;
  let lastIndex = 0;
  let match;
  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      tokens.push({ type: 'text', value: text.slice(lastIndex, match.index) });
    }
    const raw = match[0];
    if (raw.startsWith('**') && raw.endsWith('**')) {
      tokens.push({ type: 'bold', value: raw.slice(2, -2) });
    } else if (raw.startsWith('_') && raw.endsWith('_')) {
      tokens.push({ type: 'italic', value: raw.slice(1, -1) });
    }
    lastIndex = regex.lastIndex;
  }
  if (lastIndex < text.length) {
    tokens.push({ type: 'text', value: text.slice(lastIndex) });
  }
  return tokens;
};
