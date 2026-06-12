import { useState, useMemo, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import api from '../services/api';
import {
  Sparkles, RefreshCw, Check, X, Loader2, Send, FileText, Pencil, Save, Link2, Calendar, ImagePlus, Trash2, ScanLine, Undo2,
  Wand2, ChevronLeft, ChevronRight,
} from 'lucide-react';
import clsx from 'clsx';
import PageHeader from '../components/ui/PageHeader';
import StatusBadge from '../components/ui/StatusBadge';
import EmptyState from '../components/ui/EmptyState';
import ScheduleDateTimePicker from '../components/ui/ScheduleDateTimePicker';
import AffiliateLinkPicker from '../components/content/AffiliateLinkPicker';
import ContentLanguagePicker from '../components/content/ContentLanguagePicker';
import { readStoredLanguage } from '../lib/language';
import { defaultScheduleDatetimeLocal, isWithinPostingWindow, toSchedulerPayload } from '../utils/schedule';
import { confirmDelete, confirmPostedDelete, confirmRevertToDraft } from '../utils/swal';
import { useTranslation } from '../i18n';
import {
  getHookBuilderGroups,
  buildHookPrompt,
  getSelectedHookStyle,
  sanitizeHookSelections,
  toggleHookOption,
} from '../utils/hookBuilder';
const categories = [
  'story', 'product_recommendation', 'comparison', 'productivity_tip',
  'viral_hook', 'opinion', 'list_post', 'wish_i_knew', 'general',
];

const tones = [
  'casual', 'professional', 'witty', 'inspirational',
  'controversial', 'educational', 'storytelling', 'urgent',
];

const GENERATION_TABS = [
  { id: 'hook', labelKey: 'content.tabHookBuilder', icon: Wand2 },
  { id: 'full', labelKey: 'content.tabFullThread', icon: Sparkles },
];

const THREAD_LENGTHS = ['short', 'medium', 'long'];
const HOOK_COUNTS = [3, 5, 10];
const DRAFTS_PER_PAGE = 3;

export default function ContentGenerator() {
  const queryClient = useQueryClient();
  const { t, language: uiLanguage } = useTranslation();
  const [contentLanguage, setContentLanguage] = useState(() => readStoredLanguage());
  const [publishError, setPublishError] = useState(null);
  const [config, setConfig] = useState({
    niche_id: '', category: 'general', tone: '', thread_length: 'medium',
  });
  const [affiliateLinkId, setAffiliateLinkId] = useState('');
  const [fullReferencePreview, setFullReferencePreview] = useState(null);
  const [fullImageAnalysis, setFullImageAnalysis] = useState(null);
  const [editingId, setEditingId] = useState(null);
  const [editContent, setEditContent] = useState('');
  const [scheduleAtByPost, setScheduleAtByPost] = useState({});
  const [generationMode, setGenerationMode] = useState('hook');
  const [draftsPage, setDraftsPage] = useState(0);
  const [hookSelections, setHookSelections] = useState({});
  const [hookTopic, setHookTopic] = useState('');
  const [hookReferencePreview, setHookReferencePreview] = useState(null);
  const [hookImageAnalysis, setHookImageAnalysis] = useState(null);
  const [hookCount, setHookCount] = useState(5);

  const hookBuilderGroups = useMemo(() => getHookBuilderGroups(uiLanguage), [uiLanguage]);
  const hookPrompt = useMemo(
    () => buildHookPrompt(
      hookSelections,
      hookTopic,
      contentLanguage,
      hookImageAnalysis?.product_summary ?? '',
    ),
    [hookSelections, hookTopic, contentLanguage, hookImageAnalysis],
  );
  const { data: niches } = useQuery({
    queryKey: ['niches'],
    queryFn: () => api.get('/niches').then((r) => r.data.data),
  });

  const { data: affiliates } = useQuery({
    queryKey: ['affiliates'],
    queryFn: () => api.get('/affiliates').then((r) => r.data.data),
  });

  const { data: threadsAccounts } = useQuery({
    queryKey: ['threads-accounts'],
    queryFn: () => api.get('/threads/accounts').then((r) => r.data.data),
  });

  const { data: draftsResult, isLoading, isFetching } = useQuery({
    queryKey: ['content', 'drafts', draftsPage],
    queryFn: () => api.get('/content', {
      params: { limit: DRAFTS_PER_PAGE, offset: draftsPage * DRAFTS_PER_PAGE },
    }).then((r) => ({
      posts: r.data.data,
      total: r.data.total ?? 0,
    })),
    placeholderData: keepPreviousData,
  });

  const posts = draftsResult?.posts ?? [];
  const draftsTotal = draftsResult?.total ?? 0;
  const draftsTotalPages = Math.max(1, Math.ceil(draftsTotal / DRAFTS_PER_PAGE));
  const draftsFrom = draftsTotal === 0 ? 0 : draftsPage * DRAFTS_PER_PAGE + 1;
  const draftsTo = Math.min((draftsPage + 1) * DRAFTS_PER_PAGE, draftsTotal);

  // Only clamp after fetch settles — avoids resetting page while total is briefly unknown
  useEffect(() => {
    if (!draftsResult || isFetching) return;
    const maxPage = Math.max(0, Math.ceil(draftsResult.total / DRAFTS_PER_PAGE) - 1);
    if (draftsPage > maxPage) setDraftsPage(maxPage);
  }, [draftsResult, draftsPage, isFetching]);

  const isHookMode = generationMode === 'hook';

  const buildGenerateRequest = () => {
    const base = {
      niche_id: isHookMode ? undefined : (config.niche_id || undefined),
      category: isHookMode ? 'viral_hook' : config.category,
      tone: isHookMode ? undefined : (config.tone || undefined),
      affiliate_link_id: affiliateLinkId ? parseInt(affiliateLinkId, 10) : undefined,
      variations: 1,
      language: contentLanguage,
    };

    if (!isHookMode) {
      base.thread_length = config.thread_length || 'medium';
      if (fullImageAnalysis?.product_summary) {
        base.product_context = fullImageAnalysis.product_summary;
      }
    }

    if (isHookMode) {
      base.generation_mode = 'hooks_only';
      base.hook_count = hookCount;
      const hookStyle = getSelectedHookStyle(hookSelections);
      if (hookStyle) {
        base.hook_style = hookStyle;
      }
      if (hookTopic.trim()) {
        base.hook_topic = hookTopic.trim();
      }
      if (hookImageAnalysis?.product_summary) {
        base.product_context = hookImageAnalysis.product_summary;
      }
      if (hookPrompt) {
        base.hook_instruction = hookPrompt;
      }
    }

    return { type: 'json', payload: base };
  };

  const analyzeHookImageMutation = useMutation({
    mutationFn: (formData) => api.post('/content/analyze-hook-image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
    onSuccess: (res) => {
      const data = res.data?.data ?? {};
      setHookSelections(sanitizeHookSelections(data.selections ?? {}));
      if (data.topic) setHookTopic(data.topic);
      setHookImageAnalysis({
        topic: data.topic ?? '',
        product_summary: data.product_summary ?? '',
      });
    },
    onError: (err) => {
      setPublishError(err.response?.data?.message || t('content.hookAnalyzeFailed'));
    },
  });

  const analyzeThreadImageMutation = useMutation({
    mutationFn: (formData) => api.post('/content/analyze-thread-image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
    onSuccess: (res) => {
      const data = res.data?.data ?? {};
      setConfig((prev) => ({
        ...prev,
        niche_id: data.niche_id ? String(data.niche_id) : prev.niche_id,
        category: data.category || prev.category,
        tone: data.tone || prev.tone,
        thread_length: data.thread_length || prev.thread_length,
      }));
      setFullImageAnalysis({
        product_summary: data.product_summary ?? '',
        suggested_product_name: data.suggested_product_name ?? '',
        suggested_niche: data.suggested_niche ?? '',
      });
    },
    onError: (err) => {
      setPublishError(err.response?.data?.message || t('content.threadAnalyzeFailed'));
    },
  });

  const canGenerate = isHookMode
    ? Boolean(getSelectedHookStyle(hookSelections)) && !analyzeHookImageMutation.isPending
    : !analyzeThreadImageMutation.isPending;

  const generateMutation = useMutation({
    mutationFn: () => api.post('/content/generate', buildGenerateRequest().payload),
    onSuccess: () => {
      setDraftsPage(0);
      queryClient.invalidateQueries({ queryKey: ['content'] });
    },
  });

  const approveMutation = useMutation({
    mutationFn: (id) => api.put(`/content/${id}/approve`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
  });

  const deleteMutation = useMutation({
    mutationFn: ({ id, deleteFromThreads = false }) =>
      api.delete(`/content/${id}`, { data: { delete_from_threads: deleteFromThreads } }),
    onSuccess: () => {
      setEditingId(null);
      setEditContent('');
      setPublishError(null);
      queryClient.invalidateQueries({ queryKey: ['content'] });
      queryClient.invalidateQueries({ queryKey: ['scheduler'] });
    },
    onError: (err) => {
      setPublishError(err.response?.data?.message || t('common.deleteFailed'));
    },
  });

  const unapproveMutation = useMutation({
    mutationFn: (id) => api.put(`/content/${id}/unapprove`),
    onSuccess: () => {
      setEditingId(null);
      setEditContent('');
      setPublishError(null);
      queryClient.invalidateQueries({ queryKey: ['content'] });
      queryClient.invalidateQueries({ queryKey: ['scheduler'] });
    },
    onError: (err) => {
      setPublishError(err.response?.data?.message || t('common.revertFailed'));
    },
  });

  const regenerateMutation = useMutation({
    mutationFn: (id) => api.post(`/content/${id}/regenerate`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, ...body }) => api.put(`/content/${id}`, body),
    onSuccess: () => {
      setEditingId(null);
      setEditContent('');
      queryClient.invalidateQueries({ queryKey: ['content'] });
    },
  });

  const uploadHookImageMutation = useMutation({
    mutationFn: ({ id, file }) => {
      const formData = new FormData();
      formData.append('image', file);
      return api.post(`/content/${id}/hook-image`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
    onError: (err) => {
      setPublishError(err.response?.data?.message || t('common.imageUploadFailed'));
    },
  });

  const deleteHookImageMutation = useMutation({
    mutationFn: (id) => api.delete(`/content/${id}/hook-image`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
  });

  const { data: schedulerSettings } = useQuery({
    queryKey: ['scheduler-settings'],
    queryFn: () => api.get('/scheduler/settings').then((r) => r.data.data),
  });

  const publishMutation = useMutation({
    mutationFn: ({ id, affiliate_link_id }) => api.post(`/content/${id}/publish`, { affiliate_link_id: affiliate_link_id || undefined }),
    onSuccess: () => {
      setPublishError(null);
      queryClient.invalidateQueries({ queryKey: ['content'] });
    },
    onError: (err) => {
      setPublishError(err.response?.data?.message || t('common.publishFailed'));
    },
  });

  const scheduleMutation = useMutation({
    mutationFn: ({ postId, scheduled_at, account_id }) => api.post('/scheduler', {
      post_id: postId,
      account_id,
      scheduled_at: toSchedulerPayload(scheduled_at),
    }),
    onSuccess: () => {
      setPublishError(null);
      queryClient.invalidateQueries({ queryKey: ['content'] });
      queryClient.invalidateQueries({ queryKey: ['scheduler'] });
    },
    onError: (err) => {
      setPublishError(err.response?.data?.message || t('common.scheduleFailed'));
    },
  });

  const getScheduleAt = (postId) => scheduleAtByPost[postId] ?? defaultScheduleDatetimeLocal();

  const setScheduleAt = (postId, value) => {
    setScheduleAtByPost((prev) => ({ ...prev, [postId]: value }));
  };

  const threadsAccount = threadsAccounts?.[0];
  const hasThreadsAccount = !!threadsAccount;
  const canPublishChain = threadsAccount?.can_publish_reply_chain ?? false;
  const replyCount = (post) => {
    if (post.metadata?.generation_type === 'hooks_only') return null;
    return post.metadata?.replies?.length ?? null;
  };

  const hookOnlyCount = (post) => {
    if (post.metadata?.generation_type !== 'hooks_only') return null;
    return post.metadata?.hook_count ?? post.metadata?.hooks?.length ?? null;
  };
  const hasLinkPlaceholder = (post) => /\[link\]/i.test(post.content ?? '');

  const startEdit = (post) => {
    setEditingId(post.id);
    setEditContent(post.content ?? '');
  };

  const cancelEdit = () => {
    setEditingId(null);
    setEditContent('');
  };

  const saveEdit = (postId) => {
    updateMutation.mutate({ id: postId, content: editContent });
  };

  const handleUnapprove = async (post) => {
    const confirmed = await confirmRevertToDraft(post.status === 'scheduled');
    if (!confirmed) return;
    unapproveMutation.mutate(post.id);
  };

  const handleDelete = async (post) => {
    if (post.status === 'posted') {
      const choice = await confirmPostedDelete();
      if (!choice.proceed) return;
      deleteMutation.mutate({ id: post.id, deleteFromThreads: choice.deleteFromThreads });
      return;
    }

    const text = post.status === 'scheduled'
      ? t('swal.deleteScheduled')
      : t('swal.cannotUndo');
    const confirmed = await confirmDelete(text);
    if (!confirmed) return;
    deleteMutation.mutate({ id: post.id });
  };

  const isDeleting = deleteMutation.isPending;
  const isReverting = unapproveMutation.isPending;
  const canEditMedia = (status) => status === 'draft' || status === 'approved' || status === 'scheduled';

  const saveAffiliate = (postId, affiliateLinkId) => {
    updateMutation.mutate({
      id: postId,
      affiliate_link_id: affiliateLinkId ? parseInt(affiliateLinkId, 10) : null,
    });
  };

  const onPickHookImage = (postId, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setPublishError(null);
    uploadHookImageMutation.mutate({ id: postId, file });
    event.target.value = '';
  };

  const onPickFullReferenceImage = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setPublishError(null);
    if (fullReferencePreview) URL.revokeObjectURL(fullReferencePreview);
    setFullReferencePreview(URL.createObjectURL(file));
    event.target.value = '';

    const formData = new FormData();
    formData.append('reference_image', file);
    formData.append('language', contentLanguage);
    analyzeThreadImageMutation.mutate(formData);
  };

  const clearFullReferenceImage = () => {
    if (fullReferencePreview) URL.revokeObjectURL(fullReferencePreview);
    setFullReferencePreview(null);
    setFullImageAnalysis(null);
    analyzeThreadImageMutation.reset();
  };

  const clearHookReferenceImage = () => {
    if (hookReferencePreview) URL.revokeObjectURL(hookReferencePreview);
    setHookReferencePreview(null);
    setHookImageAnalysis(null);
    analyzeHookImageMutation.reset();
  };

  const onPickHookReferenceImage = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setPublishError(null);
    if (hookReferencePreview) URL.revokeObjectURL(hookReferencePreview);
    setHookReferencePreview(URL.createObjectURL(file));
    event.target.value = '';

    const formData = new FormData();
    formData.append('reference_image', file);
    formData.append('language', contentLanguage);
    analyzeHookImageMutation.mutate(formData);
  };

  const runGenerate = () => {
    setPublishError(null);
    generateMutation.mutate();
  };

  const onToggleHookOption = (groupId, optionId, multi) => {
    setHookSelections((prev) => toggleHookOption(prev, groupId, optionId, multi));
  };

  const clearHookBuilder = () => {
    setHookSelections({});
    setHookTopic('');
    clearHookReferenceImage();
  };

  return (
    <div>
      <PageHeader
        title={t('content.title')}
        description={t('content.description')}
      
      />

      {generateMutation.isError && (
        <div className="alert-error mb-6">
          {generateMutation.error?.response?.data?.message || t('common.generationFailed')}
        </div>
      )}

      {publishError && (
        <div className="alert-error mb-6">
          {publishError}
        </div>
      )}

      <div className="card mb-8 p-6">
        <div className="mb-5">
          <h2 className="text-heading mb-1 text-lg font-semibold">{t('content.newGeneration')}</h2>
          <p className="text-muted text-sm">
            {isHookMode ? t('content.newGenerationDescHook') : t('content.newGenerationDesc')}
          </p>
        </div>

        <div
          className="mb-6 inline-flex w-full flex-wrap gap-1 rounded-xl border border-slate-200 bg-slate-100/80 p-1 dark:border-slate-700 dark:bg-slate-800/60 sm:w-auto"
          role="tablist"
        >
          {GENERATION_TABS.map(({ id, labelKey, icon: Icon }) => (
            <button
              key={id}
              type="button"
              role="tab"
              aria-selected={generationMode === id}
              onClick={() => setGenerationMode(id)}
              className={clsx(
                'inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-all sm:flex-none sm:px-5',
                generationMode === id
                  ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                  : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100',
              )}
            >
              <Icon size={16} />
              {t(labelKey)}
            </button>
          ))}
        </div>

        {isHookMode ? (
          <div className="mb-5 space-y-5">
            <div className="panel-muted space-y-3 p-4">
              <div>
                <label className="text-label flex items-center gap-1.5 text-sm font-medium">
                  <ScanLine size={14} className="text-brand-500" />
                  {t('content.hookReferenceImage')}
                </label>
                <p className="text-muted mt-1 text-xs">{t('content.hookReferenceImageDesc')}</p>
              </div>

              {hookReferencePreview ? (
                <div className="flex flex-wrap items-start gap-3">
                  <div className="relative">
                    <img
                      src={hookReferencePreview}
                      alt={t('content.hookReferenceImage')}
                      className="max-h-36 max-w-full rounded-lg border border-slate-200 object-contain dark:border-slate-700"
                    />
                    {analyzeHookImageMutation.isPending && (
                      <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-900/50">
                        <Loader2 size={24} className="animate-spin text-white" />
                      </div>
                    )}
                  </div>
                  <div className="flex flex-col gap-2">
                    {analyzeHookImageMutation.isPending ? (
                      <p className="text-muted text-xs">{t('content.hookAnalyzing')}</p>
                    ) : hookImageAnalysis ? (
                      <p className="text-xs text-emerald-700 dark:text-emerald-400">{t('content.hookAnalyzedHint')}</p>
                    ) : null}
                    <button
                      type="button"
                      onClick={clearHookReferenceImage}
                      disabled={analyzeHookImageMutation.isPending}
                      className="btn-secondary !py-2 !text-xs"
                    >
                      <Trash2 size={14} /> {t('content.removeReference')}
                    </button>
                  </div>
                </div>
              ) : (
                <label className="btn-secondary !py-2 !text-xs inline-flex cursor-pointer items-center gap-2">
                  <ImagePlus size={14} />
                  {t('content.uploadReference')}
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                    className="sr-only"
                    onChange={onPickHookReferenceImage}
                  />
                </label>
              )}
            </div>

            <ContentLanguagePicker value={contentLanguage} onChange={setContentLanguage} />

            <div>
              <label className="text-label mb-1.5 block text-sm font-medium">{t('content.hookTopic')}</label>
              <input
                type="text"
                value={hookTopic}
                onChange={(e) => setHookTopic(e.target.value)}
                placeholder={t('content.hookTopicPlaceholder')}
                className="input-field"
              />
            </div>

            <div>
              <span className="text-label mb-2 block text-sm font-medium">{t('content.hookCount')}</span>
              <p className="text-muted mb-2 text-xs">{t('content.hookCountDesc')}</p>
              <div className="flex flex-wrap gap-2">
                {HOOK_COUNTS.map((count) => {
                  const selected = hookCount === count;
                  return (
                    <button
                      key={count}
                      type="button"
                      onClick={() => setHookCount(count)}
                      className={clsx(
                        'rounded-full px-3 py-1.5 text-xs font-medium transition-all',
                        selected
                          ? 'bg-brand-600 text-white shadow-sm ring-1 ring-brand-600'
                          : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-brand-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:ring-brand-500',
                      )}
                    >
                      {selected && <Check size={12} className="mr-1 inline" />}
                      {count}
                    </button>
                  );
                })}
              </div>
            </div>

            {hookBuilderGroups.map((group) => (
              <div key={group.id}>
                <div className="mb-2">
                  <span className="text-subheading text-sm font-semibold">{group.label}</span>
                  <p className="text-muted text-xs">
                    {group.description}{group.multi ? t('content.pickMultiple') : t('content.pickOne')}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  {group.options.map((opt) => {
                    const selected = (hookSelections[group.id] ?? []).includes(opt.id);
                    return (
                      <button
                        key={opt.id}
                        type="button"
                        onClick={() => onToggleHookOption(group.id, opt.id, group.multi)}
                        className={clsx(
                          'rounded-full px-3 py-1.5 text-xs font-medium transition-all',
                          selected
                            ? 'bg-brand-600 text-white shadow-sm ring-1 ring-brand-600'
                            : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-brand-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:ring-brand-500',
                        )}
                      >
                        {selected && <Check size={12} className="mr-1 inline" />}
                        {opt.label}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}

            <AffiliateLinkPicker
              value={affiliateLinkId}
              onChange={setAffiliateLinkId}
              affiliates={affiliates ?? []}
            />

            {!getSelectedHookStyle(hookSelections) && (
              <p className="text-muted text-xs">{t('content.selectHookFirst')}</p>
            )}

            {(getSelectedHookStyle(hookSelections) || hookTopic.trim()) && (
              <button
                type="button"
                onClick={clearHookBuilder}
                className="btn-secondary !py-1.5 !text-xs"
              >
                <Trash2 size={14} /> {t('common.clear')}
              </button>
            )}
          </div>
        ) : (
          <div className="mb-5 space-y-5">
            <ContentLanguagePicker value={contentLanguage} onChange={setContentLanguage} />

            <div className="panel-muted space-y-3 p-4">
              <div>
                <label className="text-label flex items-center gap-1.5 text-sm font-medium">
                  <ScanLine size={14} className="text-brand-500" />
                  {t('content.threadReferenceImage')}
                </label>
                <p className="text-muted mt-1 text-xs">{t('content.threadReferenceImageDesc')}</p>
              </div>

              {fullReferencePreview ? (
                <div className="flex flex-wrap items-start gap-3">
                  <div className="relative">
                    <img
                      src={fullReferencePreview}
                      alt={t('content.threadReferenceImage')}
                      className="max-h-36 max-w-full rounded-lg border border-slate-200 object-contain dark:border-slate-700"
                    />
                    {analyzeThreadImageMutation.isPending && (
                      <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-900/50">
                        <Loader2 size={24} className="animate-spin text-white" />
                      </div>
                    )}
                  </div>
                  <div className="flex flex-col gap-2">
                    {analyzeThreadImageMutation.isPending ? (
                      <p className="text-muted text-xs">{t('content.threadAnalyzing')}</p>
                    ) : fullImageAnalysis ? (
                      <p className="text-xs text-emerald-700 dark:text-emerald-400">{t('content.threadAnalyzedHint')}</p>
                    ) : null}
                    <button
                      type="button"
                      onClick={clearFullReferenceImage}
                      disabled={analyzeThreadImageMutation.isPending}
                      className="btn-secondary !py-2 !text-xs"
                    >
                      <Trash2 size={14} /> {t('content.removeReference')}
                    </button>
                  </div>
                </div>
              ) : (
                <label className="btn-secondary !py-2 !text-xs inline-flex cursor-pointer items-center gap-2">
                  <ImagePlus size={14} />
                  {t('content.uploadReference')}
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                    className="sr-only"
                    onChange={onPickFullReferenceImage}
                  />
                </label>
              )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label className="text-label mb-1.5 block text-sm font-medium">{t('content.niche')}</label>
                <select
                  value={config.niche_id}
                  onChange={(e) => setConfig({ ...config, niche_id: e.target.value })}
                  className="select-field"
                >
                  <option value="">{t('content.selectNiche')}</option>
                  {niches?.map((n) => (
                    <option key={n.id} value={n.id}>{n.name}</option>
                  ))}
                </select>
                {fullImageAnalysis?.suggested_niche && !config.niche_id && (
                  <p className="text-muted mt-1 text-xs">
                    {t('content.suggestedNiche')}: {fullImageAnalysis.suggested_niche}
                  </p>
                )}
              </div>
              <div>
                <label className="text-label mb-1.5 block text-sm font-medium">{t('content.category')}</label>
                <select
                  value={config.category}
                  onChange={(e) => setConfig({ ...config, category: e.target.value })}
                  className="select-field"
                >
                  {categories.map((c) => (
                    <option key={c} value={c}>{t(`content.categories.${c}`)}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-label mb-1.5 block text-sm font-medium">{t('content.tone')}</label>
                <select
                  value={config.tone}
                  onChange={(e) => setConfig({ ...config, tone: e.target.value })}
                  className="select-field"
                >
                  <option value="">{t('content.autoRotate')}</option>
                  {tones.map((tone) => (
                    <option key={tone} value={tone}>{t(`content.tones.${tone}`)}</option>
                  ))}
                </select>
              </div>
            </div>

            <div>
              <span className="text-label mb-2 block text-sm font-medium">{t('content.threadLength')}</span>
              <p className="text-muted mb-2 text-xs">{t('content.threadLengthDesc')}</p>
              <div className="flex flex-wrap gap-2">
                {THREAD_LENGTHS.map((len) => {
                  const selected = config.thread_length === len;
                  return (
                    <button
                      key={len}
                      type="button"
                      onClick={() => setConfig({ ...config, thread_length: len })}
                      className={clsx(
                        'rounded-full px-3 py-1.5 text-xs font-medium transition-all capitalize',
                        selected
                          ? 'bg-brand-600 text-white shadow-sm ring-1 ring-brand-600'
                          : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-brand-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:ring-brand-500',
                      )}
                    >
                      {selected && <Check size={12} className="mr-1 inline" />}
                      {t(`content.threadLengths.${len}`)}
                    </button>
                  );
                })}
              </div>
            </div>

            <AffiliateLinkPicker
              value={affiliateLinkId}
              onChange={setAffiliateLinkId}
              affiliates={affiliates ?? []}
              suggestedProductName={fullImageAnalysis?.suggested_product_name}
            />
          </div>
        )}

        <button
          type="button"
          onClick={runGenerate}
          disabled={generateMutation.isPending || !canGenerate}
          className="btn-primary"
        >
          {generateMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : (isHookMode ? <Wand2 size={16} /> : <Sparkles size={16} />)}
          {generateMutation.isPending
            ? t('common.generating')
            : isHookMode
              ? t('content.generateHooks', { count: hookCount })
              : t('common.generateContent')}
        </button>
      </div>

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-heading text-lg font-semibold">{t('content.recentDrafts')}</h2>
        {draftsTotal > 0 && (
          <p className="text-muted text-sm">
            {t('content.draftsShowing', { from: draftsFrom, to: draftsTo, total: draftsTotal })}
          </p>
        )}
      </div>

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <div key={i} className="card skeleton h-40" />
          ))}
        </div>
      ) : posts?.length === 0 ? (
        <div className="card">
          <EmptyState
            icon={FileText}
            title={t('content.emptyTitle')}
            description={t('content.emptyDesc')}
          />
        </div>
      ) : (
        <div className="space-y-4">
          {posts.map((post) => (
            <article key={post.id} className="card-hover overflow-hidden">
              <div className="border-b border-slate-100 bg-slate-50/50 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="flex flex-wrap gap-2">
                    <span className="badge-draft">{t(`content.categories.${post.category}`, {}) !== `content.categories.${post.category}` ? t(`content.categories.${post.category}`) : post.category?.replace(/_/g, ' ')}</span>
                    {post.tone && <span className="badge-posted">{t(`content.tones.${post.tone}`, {}) !== `content.tones.${post.tone}` ? t(`content.tones.${post.tone}`) : post.tone}</span>}
                    <span className="badge bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">{t('common.score')} {post.quality_score}</span>
                    {hookOnlyCount(post) != null && (
                      <span className="badge bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                        {hookOnlyCount(post)} {t('content.hooks')}
                      </span>
                    )}
                    {replyCount(post) != null && (
                      <span className="badge bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">{replyCount(post)} {t('common.replies')}</span>
                    )}
                    {post.metadata?.image_generation && (
                      <span className="badge bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">{t('content.fromImage')}</span>
                    )}
                  </div>
                  <StatusBadge status={post.status} />
                </div>
              </div>
              <div className="p-5">
                {editingId === post.id ? (
                  <textarea
                    value={editContent}
                    onChange={(e) => setEditContent(e.target.value)}
                    rows={14}
                    className="input-field w-full font-mono text-sm"
                    placeholder={t('content.editPlaceholder')}
                  />
                ) : (
                  <>
                  <p className="whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300 sm:text-base">
                    {post.content}
                  </p>
                
                  </>
                )}

                {canEditMedia(post.status) && (
                  <div className="panel-muted mt-4 space-y-4 p-3">
                    <div>
                      <label className="text-muted mb-2 flex items-center gap-1 text-xs font-medium">
                        <ImagePlus size={12} /> {t('content.hookImage')}
                      </label>
                      <p className="text-muted mb-3 text-xs">
                        {t('content.hookImageDesc')}
                      </p>
                      {post.hook_image_url ? (
                        <div className="flex flex-wrap items-start gap-3">
                          <img
                            src={post.hook_image_url}
                            alt={t('content.hookImage')}
                            className="max-h-40 max-w-full rounded-lg border border-slate-200 object-contain dark:border-slate-700"
                          />
                          <button
                            type="button"
                            onClick={() => deleteHookImageMutation.mutate(post.id)}
                            disabled={deleteHookImageMutation.isPending}
                            className="btn-secondary !py-2 !text-xs"
                          >
                            {deleteHookImageMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Trash2 size={14} />}
                            {t('content.removeImage')}
                          </button>
                        </div>
                      ) : (
                        <label className="btn-secondary !py-2 !text-xs inline-flex cursor-pointer items-center gap-2">
                          {uploadHookImageMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <ImagePlus size={14} />}
                          {t('content.uploadHookImage')}
                          <input
                            type="file"
                            accept="image/jpeg,image/png,.jpg,.jpeg,.png"
                            className="sr-only"
                            disabled={uploadHookImageMutation.isPending}
                            onChange={(e) => onPickHookImage(post.id, e)}
                          />
                        </label>
                      )}
                    </div>

                    <div className="flex flex-wrap items-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-700/80">
                    <div className="min-w-[12rem] flex-1">
                      <label className="text-muted mb-1 flex items-center gap-1 text-xs font-medium">
                        <Link2 size={12} /> {t('content.affiliateLink')}
                      </label>
                      <select
                        value={post.affiliate_link_id ?? ''}
                        onChange={(e) => saveAffiliate(post.id, e.target.value)}
                        className="select-field !py-2 text-sm"
                        disabled={updateMutation.isPending}
                      >
                        <option value="">{t('content.noneSharing')}</option>
                        {affiliates?.map((a) => (
                          <option key={a.id} value={a.id}>{a.product_name}</option>
                        ))}
                      </select>
                    </div>
                    {hasLinkPlaceholder(post) && !post.affiliate_link_id && (
                      <span className="text-muted text-xs">
                        {t('content.linkPlaceholderWarn')}
                      </span>
                    )}
                    </div>
                  </div>
                )}

                {post.status === 'draft' && (
                  <div className="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    {editingId === post.id ? (
                      <>
                        <button
                          type="button"
                          onClick={() => saveEdit(post.id)}
                          disabled={updateMutation.isPending}
                          className="btn-primary !py-2 !text-xs"
                        >
                          {updateMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
                          {t('common.saveEdits')}
                        </button>
                        <button type="button" onClick={cancelEdit} className="btn-secondary !py-2 !text-xs">
                          {t('common.cancel')}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDelete(post)}
                          disabled={isDeleting}
                          className="btn-danger !py-2 !text-xs"
                        >
                          {isDeleting ? <Loader2 size={14} className="animate-spin" /> : <Trash2 size={14} />}
                          {t('common.delete')}
                        </button>
                      </>
                    ) : (
                      <>
                        <button
                          type="button"
                          onClick={() => startEdit(post)}
                          className="btn-secondary !py-2 !text-xs"
                        >
                          <Pencil size={14} /> {t('common.edit')}
                        </button>
                        <button
                      type="button"
                      onClick={() => regenerateMutation.mutate(post.id)}
                      disabled={regenerateMutation.isPending}
                      className="btn-secondary !py-2 !text-xs"
                    >
                      {regenerateMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <RefreshCw size={14} />}
                      {t('common.regenerate')}
                    </button>
                        <button
                          type="button"
                          onClick={() => handleDelete(post)}
                          disabled={isDeleting}
                          className="btn-danger !py-2 !text-xs"
                        >
                          <X size={14} /> {t('common.delete')}
                        </button>
                      </>
                    )}
                    <button
                      type="button"
                      onClick={() => approveMutation.mutate(post.id)}
                      disabled={approveMutation.isPending}
                      className="btn-success !py-2 !text-xs"
                    >
                      <Check size={14} /> {t('common.approve')}
                    </button>
                 
                  </div>
                )}

                {post.status === 'scheduled' && (
                  <>
                    <p className="mt-4 flex flex-wrap items-center gap-2 text-sm text-amber-800 dark:text-amber-300">
                      <Calendar size={16} />
                      {t('content.queuedPublish')}
                      <Link to="/scheduler" className="font-semibold underline">{t('content.viewQueue')}</Link>
                    </p>
                    <div className="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                      {editingId === post.id ? (
                        <>
                          <button
                            type="button"
                            onClick={() => saveEdit(post.id)}
                            disabled={updateMutation.isPending}
                            className="btn-primary !py-2 !text-xs"
                          >
                            {updateMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
                            {t('common.saveEdits')}
                          </button>
                          <button type="button" onClick={cancelEdit} className="btn-secondary !py-2 !text-xs">
                            {t('common.cancel')}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleUnapprove(post)}
                            disabled={isReverting}
                            className="btn-secondary !py-2 !text-xs"
                          >
                            {isReverting ? <Loader2 size={14} className="animate-spin" /> : <Undo2 size={14} />}
                            {t('common.revertToDraft')}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(post)}
                            disabled={isDeleting}
                            className="btn-danger !py-2 !text-xs"
                          >
                            {isDeleting ? <Loader2 size={14} className="animate-spin" /> : <Trash2 size={14} />}
                            {t('common.delete')}
                          </button>
                        </>
                      ) : (
                        <>
                          <button
                            type="button"
                            onClick={() => startEdit(post)}
                            className="btn-secondary !py-2 !text-xs"
                          >
                            <Pencil size={14} /> {t('common.edit')}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleUnapprove(post)}
                            disabled={isReverting}
                            className="btn-secondary !py-2 !text-xs"
                          >
                            <Undo2 size={14} /> {t('common.revertToDraft')}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(post)}
                            disabled={isDeleting}
                            className="btn-danger !py-2 !text-xs"
                          >
                            <Trash2 size={14} /> {t('common.delete')}
                          </button>
                        </>
                      )}
                    </div>
                  </>
                )}

                {post.status === 'approved' && (
                  <>
                  <div className="panel-muted mt-4 p-4">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2">
                        <Calendar size={16} className="text-brand-600 dark:text-brand-400" />
                        <span className="text-subheading text-sm font-semibold">{t('content.schedulePublish')}</span>
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          setPublishError(null);
                          scheduleMutation.mutate({
                            postId: post.id,
                            scheduled_at: getScheduleAt(post.id),
                            account_id: threadsAccount.id,
                          });
                        }}
                        disabled={
                          scheduleMutation.isPending
                          || !hasThreadsAccount
                          || (schedulerSettings && !isWithinPostingWindow(getScheduleAt(post.id), schedulerSettings))
                        }
                        className="btn-primary !py-2 !text-xs shrink-0"
                        title={
                          !hasThreadsAccount
                            ? t('content.titleConnectThreads')
                            : t('content.titleRequiresCron')
                        }
                      >
                        {scheduleMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Calendar size={14} />}
                        {t('common.schedulePost')}
                      </button>
                    </div>

                    <ScheduleDateTimePicker
                      value={getScheduleAt(post.id)}
                      onChange={(v) => setScheduleAt(post.id, v)}
                      disabled={!hasThreadsAccount || scheduleMutation.isPending}
                      settings={schedulerSettings}
                    />

                   
                  </div>

                  <div className="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    {editingId === post.id ? (
                      <>
                        <button
                          type="button"
                          onClick={() => saveEdit(post.id)}
                          disabled={updateMutation.isPending}
                          className="btn-primary !py-2 !text-xs"
                        >
                          {updateMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
                          {t('common.saveEdits')}
                        </button>
                        <button type="button" onClick={cancelEdit} className="btn-secondary !py-2 !text-xs">
                          {t('common.cancel')}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleUnapprove(post)}
                          disabled={isReverting}
                          className="btn-secondary !py-2 !text-xs"
                        >
                          {isReverting ? <Loader2 size={14} className="animate-spin" /> : <Undo2 size={14} />}
                          {t('common.revertToDraft')}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDelete(post)}
                          disabled={isDeleting}
                          className="btn-danger !py-2 !text-xs"
                        >
                          {isDeleting ? <Loader2 size={14} className="animate-spin" /> : <Trash2 size={14} />}
                          {t('common.delete')}
                        </button>
                      </>
                    ) : (
                      <>
                        <button
                          type="button"
                          onClick={() => startEdit(post)}
                          className="btn-secondary !py-2 !text-xs"
                        >
                          <Pencil size={14} /> {t('common.edit')}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleUnapprove(post)}
                          disabled={isReverting}
                          className="btn-secondary !py-2 !text-xs"
                        >
                          <Undo2 size={14} /> {t('common.revertToDraft')}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDelete(post)}
                          disabled={isDeleting}
                          className="btn-danger !py-2 !text-xs"
                        >
                          <Trash2 size={14} /> Delete
                        </button>
                      </>
                    )}
                    <button
                      type="button"
                      onClick={() => {
                        setPublishError(null);
                        publishMutation.mutate({
                          id: post.id,
                          affiliate_link_id: post.affiliate_link_id,
                        });
                      }}
                      disabled={
                        publishMutation.isPending
                        || !hasThreadsAccount
                        || !canPublishChain
                      }
                      className="btn-primary !py-2 !text-xs"
                      title={
                        !hasThreadsAccount
                          ? t('content.titleConnectThreads')
                          : !canPublishChain
                            ? t('content.titleReconnectScopes')
                            : t('content.titlePublishChain')
                      }
                    >
                      {publishMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Send size={14} />}
                      {t('common.publishNow')}
                    </button>
                    {!hasThreadsAccount && (
                      <span className="text-muted text-xs">{t('content.connectThreads')}</span>
                    )}
                    {hasThreadsAccount && !canPublishChain && (
                      <span className="text-xs font-medium text-red-600 dark:text-red-400">{t('content.reconnectSettings')}</span>
                    )}
                  </div>
                  </>
                )}

                {post.status === 'posted' && post.metadata?.threads_publish && (
                  <>
                    <p className="mt-4 flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                      <span className="h-2 w-2 rounded-full bg-emerald-500" />
                      {t('content.liveOnThreads', { count: post.metadata.threads_publish.published_count })}
                    </p>
                    <div className="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                      {editingId === post.id ? (
                        <>
                          <button
                            type="button"
                            onClick={() => saveEdit(post.id)}
                            disabled={updateMutation.isPending}
                            className="btn-primary !py-2 !text-xs"
                          >
                            {updateMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
                            {t('common.saveEdits')}
                          </button>
                          <button type="button" onClick={cancelEdit} className="btn-secondary !py-2 !text-xs">
                            {t('common.cancel')}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(post)}
                            disabled={isDeleting}
                            className="btn-danger !py-2 !text-xs"
                          >
                            {isDeleting ? <Loader2 size={14} className="animate-spin" /> : <Trash2 size={14} />}
                            {t('common.delete')}
                          </button>
                        </>
                      ) : (
                        <>
                          <button
                            type="button"
                            onClick={() => startEdit(post)}
                            className="btn-secondary !py-2 !text-xs"
                          >
                            <Pencil size={14} /> {t('common.edit')}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(post)}
                            disabled={isDeleting}
                            className="btn-danger !py-2 !text-xs"
                            title={t('content.titleDeleteChoice')}
                          >
                            <Trash2 size={14} /> {t('common.delete')}
                          </button>
                        </>
                      )}
                      <span className="text-muted self-center text-xs">
                        {t('content.deleteDialogHint')}
                      </span>
                    </div>
                  </>
                )}
              </div>
            </article>
          ))}

          {draftsTotalPages > 1 && (
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
              <button
                type="button"
                onClick={() => setDraftsPage((p) => Math.max(0, p - 1))}
                disabled={draftsPage === 0}
                className="btn-secondary !py-2 !text-xs"
              >
                <ChevronLeft size={16} /> {t('common.previous')}
              </button>
              <span className="text-muted text-sm">
                {t('content.draftsPage', { page: draftsPage + 1, total: draftsTotalPages })}
              </span>
              <button
                type="button"
                onClick={() => setDraftsPage((p) => Math.min(draftsTotalPages - 1, p + 1))}
                disabled={draftsPage >= draftsTotalPages - 1}
                className="btn-secondary !py-2 !text-xs"
              >
                {t('common.next')} <ChevronRight size={16} />
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
