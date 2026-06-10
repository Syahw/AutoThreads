import { normalizeLanguage } from '../lib/language';

const HOOK_BUILDER_GROUPS_I18N = [
  {
    id: 'hook_style',
    label: { bm: 'Gaya hook', en: 'Hook style' },
    description: { bm: 'Pilih satu gaya untuk Reply 1', en: 'Pick one style for Reply 1' },
    multi: false,
    options: [
      {
        id: 'storytelling',
        label: { bm: 'Storytelling', en: 'Storytelling' },
        prompt: {
          bm: 'Gaya storytelling — mula dengan cerita, specific moment, atau pengalaman peribadi yang tarik reader masuk.',
          en: 'Storytelling style — open with a story, specific moment, or personal experience that pulls readers in.',
        },
      },
      {
        id: 'fomo',
        label: { bm: 'FOMO', en: 'FOMO' },
        prompt: {
          bm: 'Gaya FOMO — highlight apa yang orang lain dah dapat atau benefit yang reader mungkin terlepas.',
          en: 'FOMO style — highlight what others are already getting or the benefit the reader might miss out on.',
        },
      },
      {
        id: 'urgency',
        label: { bm: 'Urgency', en: 'Urgency' },
        prompt: {
          bm: 'Gaya urgency — tekanan masa atau peluang terhad, contoh: "kalau korang tak beli sekarang rugi weh sebab harga tengah murah". Natural, bukan caps lock atau hype berlebihan.',
          en: 'Urgency style — time pressure or limited opportunity, e.g. "if you don\'t grab this now you\'re missing out while the price is low". Natural tone, no caps lock or over-hype.',
        },
      },
      {
        id: 'problem_solution',
        label: { bm: 'Problem Solution', en: 'Problem Solution' },
        prompt: {
          bm: 'Gaya problem-solution — mula dengan masalah atau pain point yang relatable, kemudian hint penyelesaian.',
          en: 'Problem-solution style — start with a relatable problem or pain point, then hint at the solution.',
        },
      },
      {
        id: 'self_thought',
        label: { bm: 'Self thought / sharing', en: 'Self thought / sharing' },
        prompt: {
          bm: 'Gaya self thought / sharing — kongsi pemikiran peribadi, pendapat, atau pengalaman macam berfikir kuat-kuat dengan kawan.',
          en: 'Self thought / sharing style — share a personal thought, opinion, or experience like thinking out loud with a friend.',
        },
      },
    ],
  },
];

const PROMPT_COPY = {
  bm: {
    header: 'HOOK INSTRUCTION (Reply 1):',
    intro: 'Tulis hook yang ikut arahan di bawah. Hook mesti dalam Bahasa Malaysia santai, ejaan betul.',
    topic: 'TOPIK / SUBJEK',
    output: 'OUTPUT: Hanya Reply 1 (hook). Jangan tulis reply lain. Jangan tambah intro/outro.',
    pickMultiple: '— pilih banyak',
    pickOne: '— pilih satu',
  },
  en: {
    header: 'HOOK INSTRUCTION (Reply 1):',
    intro: 'Write a hook following the instructions below. The hook must be in natural, casual English.',
    topic: 'TOPIC / SUBJECT',
    output: 'OUTPUT: Reply 1 (hook) only. Do not write other replies. No intro or outro.',
    pickMultiple: '— pick multiple',
    pickOne: '— pick one',
  },
};

/**
 * @param {'bm'|'en'} language
 */
export function getHookBuilderGroups(language = 'bm') {
  const lang = normalizeLanguage(language);

  return HOOK_BUILDER_GROUPS_I18N.map((group) => ({
    id: group.id,
    label: group.label[lang],
    description: group.description[lang],
    multi: group.multi,
    options: group.options.map((opt) => ({
      id: opt.id,
      label: opt.label[lang],
      prompt: opt.prompt[lang],
    })),
  }));
}

/** @deprecated use getHookBuilderGroups */
export const HOOK_BUILDER_GROUPS = getHookBuilderGroups('bm');

/**
 * @param {Record<string, string[]>} selections
 * @param {string} [topic]
 * @param {'bm'|'en'} [language]
 */
export function buildHookPrompt(selections, topic = '', language = 'bm', productContext = '') {
  const lang = normalizeLanguage(language);
  const copy = PROMPT_COPY[lang];
  const groups = getHookBuilderGroups(lang);
  const lines = [];

  lines.push(copy.header);
  lines.push(copy.intro);

  const context = productContext.trim();
  if (context) {
    lines.push('');
    lines.push(`PRODUCT CONTEXT: ${context}`);
  }

  if (topic.trim()) {
    lines.push('');
    lines.push(`${copy.topic}: ${topic.trim()}`);
  }

  const sections = [];

  for (const group of groups) {
    const selectedIds = selections[group.id] ?? [];
    if (selectedIds.length === 0) continue;

    const prompts = group.options
      .filter((opt) => selectedIds.includes(opt.id))
      .map((opt) => opt.prompt);

    if (prompts.length > 0) {
      sections.push({ label: group.label, prompts });
    }
  }

  if (sections.length === 0 && !topic.trim()) {
    return '';
  }

  for (const section of sections) {
    lines.push('');
    lines.push(`${section.label.toUpperCase()}:`);
    section.prompts.forEach((p) => lines.push(`- ${p}`));
  }

  lines.push('');
  lines.push(copy.output);

  return lines.join('\n');
}

export function getHookBuilderCopy(language = 'bm') {
  const lang = normalizeLanguage(language);
  return {
    ...PROMPT_COPY[lang],
    title: lang === 'bm' ? 'Hook builder' : 'Hook builder',
    subtitle: lang === 'bm'
      ? 'Pilih gaya hook untuk Reply 1 — dapat prompt siap untuk copy atau guna semasa generate.'
      : 'Pick a hook style for Reply 1 — get a ready-made prompt to copy or use when generating.',
    topicLabel: lang === 'bm' ? 'Topik / subjek (pilihan)' : 'Topic / subject (optional)',
    topicPlaceholder: lang === 'bm'
      ? 'cth. tips affiliate Shopee, gaming setup, morning routine'
      : 'e.g. Shopee affiliate tips, gaming setup, morning routine',
    promptLabel: lang === 'bm' ? 'Prompt hook dijana' : 'Generated hook prompt',
    copyPrompt: lang === 'bm' ? 'Copy prompt' : 'Copy prompt',
    copied: lang === 'bm' ? 'Disalin' : 'Copied',
    clear: lang === 'bm' ? 'Kosongkan' : 'Clear',
    useWhenGenerating: lang === 'bm'
      ? 'Guna prompt hook ini semasa generate'
      : 'Use this hook prompt when generating',
    selectFirst: lang === 'bm'
      ? 'Pilih sekurang-kurangnya satu pilihan atau tambah topik dahulu'
      : 'Select at least one option or add a topic first',
    placeholder: lang === 'bm'
      ? 'Pilih pilihan di atas untuk bina prompt hook…'
      : 'Select options above to build your hook prompt…',
    active: lang === 'bm' ? 'Aktif' : 'Active',
  };
}

/**
 * @param {Record<string, string[]>} selections
 */
export function hasHookSelections(selections) {
  return HOOK_BUILDER_GROUPS_I18N.some((g) => (selections[g.id]?.length ?? 0) > 0);
}

/**
 * @param {Record<string, string[]>} selections
 * @param {string} groupId
 * @param {string} optionId
 * @param {boolean} multi
 */
/**
 * @param {Record<string, string[]>} raw
 * @returns {Record<string, string[]>}
 */
export function sanitizeHookSelections(raw) {
  const groups = getHookBuilderGroups('en');
  const result = {};

  for (const group of groups) {
    const valid = new Set(group.options.map((o) => o.id));
    const incoming = Array.isArray(raw?.[group.id]) ? raw[group.id] : [];
    let picked = incoming.filter((id) => valid.has(id));

    if (!group.multi && picked.length > 1) {
      picked = [picked[0]];
    }

    if (picked.length > 0) {
      result[group.id] = picked;
    }
  }

  return result;
}

export function toggleHookOption(selections, groupId, optionId, multi) {
  const current = selections[groupId] ?? [];
  const isSelected = current.includes(optionId);

  if (!multi) {
    return { ...selections, [groupId]: isSelected ? [] : [optionId] };
  }

  const next = isSelected
    ? current.filter((id) => id !== optionId)
    : [...current, optionId];

  return { ...selections, [groupId]: next };
}
