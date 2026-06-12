import { normalizeLanguage } from '../lib/language';

const GLOBAL_HOOK_RULES = {
  bm: [
    'Bunyi macam orang Malaysia sebenar di social media — natural, conversational, relatable.',
    'Elak bahasa robot, generic, atau terlalu jualan.',
    'Elak emoji berlebihan. Ayat pendek, punchy. Max 15 patah perkataan untuk Reply 1.',
    'Cipta curiosity. Rasa macam pengalaman peribadi, pemerhatian, masalah, atau cadangan.',
    'JANGAN mula setiap hook dengan struktur sama. Vary pattern ayat secara natural.',
    'Elak ALL CAPS. Elak "anda" — prefer "kau", "korang", atau BM santai.',
    'Bunyi macam UGC authentic, bukan iklan. Jangan clickbait palsu.',
  ],
  en: [
    'Write like a real Malaysian on social media — natural, conversational, relatable.',
    'Avoid robotic, generic, or overly salesy language.',
    'Avoid excessive emojis. Short, punchy sentences. Max 15 words for Reply 1.',
    'Create curiosity. Feel like personal experience, observation, problem, or recommendation.',
    'Do NOT start every hook with the same structure. Vary sentence patterns.',
    'Avoid ALL CAPS. Avoid "anda" — prefer "kau", "korang", or natural spoken BM.',
    'Sound like authentic UGC, not ad copy. No fake clickbait.',
  ],
};

const HOOK_STYLES = {
  storytelling: {
    label: { bm: 'Storytelling', en: 'Storytelling' },
    purpose: {
      bm: 'Mula dengan pengalaman peribadi, journey, atau transformation.',
      en: 'Start with a personal experience, journey, or transformation.',
    },
    formula: {
      bm: 'Situasi lalu → Turning point → Curiosity',
      en: 'Past situation → Turning point → Curiosity',
    },
    examples: {
      bm: [
        'Aku ingat lagi kali pertama cuba benda ni...',
        'Mula-mula aku ingat benda ni cuma gimik...',
        'Ada satu masa tu aku hampir give up...',
        'Tak sangka benda simple macam ni boleh ubah rutin aku.',
      ],
      en: [
        'I still remember the first time I tried this...',
        'At first I thought this was just hype...',
        'There was a point I almost gave up...',
        "Didn't expect something this simple could change my routine.",
      ],
    },
  },
  fomo: {
    label: { bm: 'FOMO', en: 'FOMO' },
    purpose: {
      bm: 'Buat orang rasa mungkin terlepas peluang.',
      en: 'Make people feel they might be missing out.',
    },
    formula: {
      bm: 'Orang lain dah benefit → Reader mungkin miss peluang',
      en: 'Others are benefiting → Reader may miss the opportunity',
    },
    examples: {
      bm: [
        'Ramai orang dah guna benda ni, kau masih belum cuba?',
        'Baru faham kenapa produk ni asyik sold out.',
        'Kalau kau nampak ni sekarang, jangan tunggu lama.',
      ],
      en: [
        'A lot of people are already using this — have you tried it yet?',
        'Finally get why this keeps selling out.',
        "If you're seeing this now, don't wait too long.",
      ],
    },
  },
  urgency: {
    label: { bm: 'Urgency', en: 'Urgency' },
    purpose: {
      bm: 'Galakkan tindakan segera tanpa hype palsu.',
      en: 'Encourage immediate action without fake hype.',
    },
    formula: {
      bm: 'Masa terhad → Potential loss → Action',
      en: 'Limited time → Potential loss → Action',
    },
    examples: {
      bm: [
        'Kalau nak beli, minggu ni mungkin masa paling sesuai.',
        'Promo ni tinggal beberapa hari je lagi.',
        'Jangan tunggu harga naik baru nak menyesal.',
        'Kalau korang tak beli sekarang rugi weh sebab harga tengah murah.',
      ],
      en: [
        "If you're buying, this week might be the sweet spot.",
        'This promo only has a few days left.',
        "Don't wait till the price goes up to regret it.",
        "If you don't grab this now you're missing out while it's cheap.",
      ],
    },
  },
  problem_solution: {
    label: { bm: 'Problem Solution', en: 'Problem Solution' },
    purpose: {
      bm: 'Highlight pain point dan hint penyelesaian.',
      en: 'Highlight a pain point and hint at a solution.',
    },
    formula: {
      bm: 'Masalah → Frustration → Solution teaser',
      en: 'Problem → Frustration → Solution teaser',
    },
    examples: {
      bm: [
        'Penat dah cuba macam-macam tapi hasil tetap sama?',
        'Kalau kau selalu hadap masalah ni, cuba tengok ni.',
        'Rupanya punca masalah ni bukan macam yang aku sangka.',
      ],
      en: [
        'Tired of trying everything but getting the same results?',
        'If you keep running into this problem, look at this.',
        "Turns out the root cause wasn't what I thought.",
      ],
    },
  },
  self_thought: {
    label: { bm: 'Self thought / sharing', en: 'Self thought / sharing' },
    purpose: {
      bm: 'Kongsi pendapat peribadi atau pemerhatian.',
      en: 'Share personal opinions or observations.',
    },
    formula: {
      bm: 'Personal thought → Observation → Insight',
      en: 'Personal thought → Observation → Insight',
    },
    examples: {
      bm: [
        'Aku rasa ramai orang sebenarnya tak sedar benda ni.',
        'Selepas beberapa minggu guna, ini pendapat aku.',
        'Aku perasan semakin ramai orang mula buat macam ni.',
      ],
      en: [
        "I think a lot of people don't actually realize this.",
        "After a few weeks of using it, here's my take.",
        "I've noticed more people starting to do this.",
      ],
    },
  },
};

const HOOK_BUILDER_GROUPS_I18N = [
  {
    id: 'hook_style',
    label: { bm: 'Gaya hook', en: 'Hook style' },
    description: { bm: 'Pilih satu gaya untuk Reply 1', en: 'Pick one style for Reply 1' },
    multi: false,
    options: Object.entries(HOOK_STYLES).map(([id, style]) => ({
      id,
      label: style.label,
    })),
  },
];

const PROMPT_COPY = {
  bm: {
    header: 'HOOK INSTRUCTION (Reply 1):',
    hookStyle: 'GAYA HOOK',
    purpose: 'TUJUAN',
    formula: 'FORMULA',
    examples: 'CONTOH (inspirasi sahaja — vary, jangan copy)',
    topic: 'TOPIK',
    output: 'OUTPUT: Hasilkan hook sahaja (bukan thread). Setiap hook max 15 patah perkataan. Jangan sebut produk dalam hook.',
    pickMultiple: '— pilih banyak',
    pickOne: '— pilih satu',
  },
  en: {
    header: 'HOOK INSTRUCTION (Reply 1):',
    hookStyle: 'HOOK STYLE',
    purpose: 'PURPOSE',
    formula: 'FORMULA',
    examples: 'EXAMPLES (inspiration only — vary, do not copy)',
    topic: 'TOPIC',
    output: 'OUTPUT: Generate hooks only (not a thread). Each hook max 15 words. Do not mention the product in hooks.',
    pickMultiple: '— pick multiple',
    pickOne: '— pick one',
  },
};

function getStyleDefinition(styleId, language) {
  return HOOK_STYLES[styleId] ?? null;
}

function buildStyleInstruction(styleId, language) {
  const lang = normalizeLanguage(language);
  const style = getStyleDefinition(styleId, lang);
  if (!style) return '';

  const copy = PROMPT_COPY[lang];
  const lines = [
    copy.header,
    ...GLOBAL_HOOK_RULES[lang],
    '',
    `${copy.hookStyle}: ${style.label[lang]}`,
    `${copy.purpose}: ${style.purpose[lang]}`,
    `${copy.formula}: ${style.formula[lang]}`,
    `${copy.examples}:`,
    ...style.examples[lang].map((ex) => `- ${ex}`),
  ];

  return lines.join('\n');
}

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
      prompt: buildStyleInstruction(opt.id, lang),
    })),
  }));
}

/** @deprecated use getHookBuilderGroups */
export const HOOK_BUILDER_GROUPS = getHookBuilderGroups('bm');

/**
 * @param {Record<string, string[]>} selections
 * @returns {string|null}
 */
export function getSelectedHookStyle(selections) {
  const picked = selections?.hook_style?.[0];
  return picked && HOOK_STYLES[picked] ? picked : null;
}

/**
 * @param {Record<string, string[]>} selections
 * @param {string} [topic]
 * @param {'bm'|'en'} [language]
 */
export function buildHookPrompt(selections, topic = '', language = 'bm', productContext = '') {
  const lang = normalizeLanguage(language);
  const copy = PROMPT_COPY[lang];
  const styleId = getSelectedHookStyle(selections);

  if (!styleId && !topic.trim()) {
    return '';
  }

  const lines = [];

  if (styleId) {
    lines.push(buildStyleInstruction(styleId, lang));
  } else {
    lines.push(copy.header);
    lines.push(...GLOBAL_HOOK_RULES[lang]);
  }

  const context = productContext.trim();
  if (context) {
    lines.push('');
    lines.push(`PRODUCT CONTEXT: ${context}`);
  }

  if (topic.trim()) {
    lines.push('');
    lines.push(`${copy.topic}: ${topic.trim()}`);
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
      ? 'Pilih gaya hook untuk Reply 1 — setiap gaya ada formula & contoh tersendiri.'
      : 'Pick a hook style for Reply 1 — each style has its own formula and examples.',
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
      ? 'Pilih gaya hook di atas untuk bina prompt…'
      : 'Select a hook style above to build your prompt…',
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
