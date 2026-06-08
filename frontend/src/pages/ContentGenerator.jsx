import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import {
  Sparkles, RefreshCw, Check, X, Loader2, Send, FileText, Pencil, Save, Link2, Calendar, ImagePlus, Trash2, ScanLine,
} from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import StatusBadge from '../components/ui/StatusBadge';
import EmptyState from '../components/ui/EmptyState';
import ScheduleDateTimePicker from '../components/ui/ScheduleDateTimePicker';
import { defaultScheduleDatetimeLocal, isWithinPostingWindow, toSchedulerPayload } from '../utils/schedule';

const categories = [
  'story', 'product_recommendation', 'comparison', 'productivity_tip',
  'viral_hook', 'opinion', 'list_post', 'wish_i_knew', 'general',
];

const tones = [
  'casual', 'professional', 'witty', 'inspirational',
  'controversial', 'educational', 'storytelling', 'urgent',
];

export default function ContentGenerator() {
  const queryClient = useQueryClient();
  const [publishError, setPublishError] = useState(null);
  const [config, setConfig] = useState({
    niche_id: '', category: 'general', tone: '', variations: 1, affiliate_link_id: '',
  });
  const [referenceImage, setReferenceImage] = useState(null);
  const [referencePreview, setReferencePreview] = useState(null);
  const [highDetail, setHighDetail] = useState(false);
  const [lastImageAnalysis, setLastImageAnalysis] = useState(null);
  const [editingId, setEditingId] = useState(null);
  const [editContent, setEditContent] = useState('');
  const [scheduleAtByPost, setScheduleAtByPost] = useState({});

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

  const { data: posts, isLoading } = useQuery({
    queryKey: ['content'],
    queryFn: () => api.get('/content?limit=10').then((r) => r.data.data),
  });

  const { data: visionSettings } = useQuery({
    queryKey: ['vision-settings'],
    queryFn: () => api.get('/content/vision-settings').then((r) => r.data.data),
  });

  const buildGenerateRequest = () => {
    const base = {
      niche_id: config.niche_id || undefined,
      category: config.category,
      tone: config.tone || undefined,
      affiliate_link_id: config.affiliate_link_id ? parseInt(config.affiliate_link_id, 10) : undefined,
      variations: referenceImage ? 1 : config.variations,
    };

    if (!referenceImage) {
      return { type: 'json', payload: base };
    }

    const formData = new FormData();
    Object.entries(base).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        formData.append(key, String(value));
      }
    });
    formData.append('reference_image', referenceImage);
    formData.append('high_detail', highDetail ? '1' : '0');

    return { type: 'multipart', payload: formData };
  };

  const generateMutation = useMutation({
    mutationFn: () => {
      const req = buildGenerateRequest();
      if (req.type === 'multipart') {
        return api.post('/content/generate', req.payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
      }
      return api.post('/content/generate', req.payload);
    },
    onSuccess: (res) => {
      setLastImageAnalysis(res.data?.image_analysis ?? null);
      if (res.data?.image_analysis) {
        setReferencePreview((prev) => {
          if (prev) URL.revokeObjectURL(prev);
          return null;
        });
        setReferenceImage(null);
        setHighDetail(false);
      }
      queryClient.invalidateQueries({ queryKey: ['content'] });
    },
    onError: () => setLastImageAnalysis(null),
  });

  const approveMutation = useMutation({
    mutationFn: (id) => api.put(`/content/${id}/approve`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
  });

  const rejectMutation = useMutation({
    mutationFn: (id) => api.delete(`/content/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
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
      setPublishError(err.response?.data?.message || 'Image upload failed');
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
      setPublishError(err.response?.data?.message || 'Publish failed');
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
      setPublishError(err.response?.data?.message || 'Schedule failed');
    },
  });

  const getScheduleAt = (postId) => scheduleAtByPost[postId] ?? defaultScheduleDatetimeLocal();

  const setScheduleAt = (postId, value) => {
    setScheduleAtByPost((prev) => ({ ...prev, [postId]: value }));
  };

  const threadsAccount = threadsAccounts?.[0];
  const hasThreadsAccount = !!threadsAccount;
  const canPublishChain = threadsAccount?.can_publish_reply_chain ?? false;
  const replyCount = (post) => post.metadata?.replies?.length ?? null;
  const hasLinkPlaceholder = (post) => /\[link\]/i.test(post.content ?? '');

  const startEdit = (post) => {
    setEditingId(post.id);
    setEditContent(post.content ?? '');
  };

  const saveEdit = (postId) => {
    updateMutation.mutate({ id: postId, content: editContent });
  };

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

  const onPickReferenceImage = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setPublishError(null);
    setReferenceImage(file);
    setReferencePreview(URL.createObjectURL(file));
    setConfig((prev) => ({ ...prev, variations: 1 }));
    event.target.value = '';
  };

  const clearReferenceImage = () => {
    if (referencePreview) URL.revokeObjectURL(referencePreview);
    setReferenceImage(null);
    setReferencePreview(null);
    setHighDetail(false);
  };

  const runGenerate = () => {
    setPublishError(null);
    generateMutation.mutate();
  };

  return (
    <div>
      <PageHeader
        title="Content generator"
        description="Create AI thread drafts, approve, schedule for later, or publish immediately to Threads."
        action={
          <button
            type="button"
            onClick={runGenerate}
            disabled={generateMutation.isPending}
            className="btn-primary"
          >
            {generateMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Sparkles size={16} />}
            {generateMutation.isPending ? 'Generating...' : 'Quick generate'}
          </button>
        }
      />

      {generateMutation.isError && (
        <div className="alert-error mb-6">
          {generateMutation.error?.response?.data?.message || 'Generation failed'}
        </div>
      )}

      {lastImageAnalysis && (
        <div className="alert-success mb-6">
          Generated from image
         
          {lastImageAnalysis.extracted_text && (
            <p className="mt-2 text-left text-xs opacity-90">
              <span className="font-semibold">Extracted text:</span> {lastImageAnalysis.extracted_text.slice(0, 200)}
              {lastImageAnalysis.extracted_text.length > 200 ? '…' : ''}
            </p>
          )}
        </div>
      )}

      {publishError && (
        <div className="alert-error mb-6">
          {publishError}
        </div>
      )}

      <div className="card mb-8 p-6">
        <h2 className="text-heading mb-1 text-lg font-semibold">New generation</h2>
        <p className="text-muted mb-5 text-sm">Configure niche, category, and tone for your next thread.</p>
        <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <div>
            <label className="text-label mb-1.5 block text-sm font-medium">Niche</label>
            <select
              value={config.niche_id}
              onChange={(e) => setConfig({ ...config, niche_id: e.target.value })}
              className="select-field"
            >
              <option value="">Select niche</option>
              {niches?.map((n) => (
                <option key={n.id} value={n.id}>{n.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-label mb-1.5 block text-sm font-medium">Category</label>
            <select
              value={config.category}
              onChange={(e) => setConfig({ ...config, category: e.target.value })}
              className="select-field"
            >
              {categories.map((c) => (
                <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-label mb-1.5 block text-sm font-medium">Tone</label>
            <select
              value={config.tone}
              onChange={(e) => setConfig({ ...config, tone: e.target.value })}
              className="select-field"
            >
              <option value="">Auto-rotate</option>
              {tones.map((t) => (
                <option key={t} value={t}>{t}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-label mb-1.5 block text-sm font-medium">Variations</label>
            <input
              type="number"
              min="1"
              max="5"
              value={config.variations}
              onChange={(e) => setConfig({ ...config, variations: parseInt(e.target.value, 10) || 1 })}
              disabled={!!referenceImage}
              className="input-field"
              title={referenceImage ? 'Variations disabled when using a reference image' : undefined}
            />
          </div>
          <div>
            <label className="text-label mb-1.5 block text-sm font-medium">Affiliate link (optional)</label>
            <select
              value={config.affiliate_link_id}
              onChange={(e) => setConfig({ ...config, affiliate_link_id: e.target.value })}
              className="select-field"
            >
              <option value="">None — sharing only</option>
              {affiliates?.map((a) => (
                <option key={a.id} value={a.id}>{a.product_name}</option>
              ))}
            </select>
          </div>
        </div>
        {config.affiliate_link_id ? (
          <p className="text-muted mb-4 text-sm">
            AI will put <code className="code-inline">[link]</code> in the last reply. Your real URL is inserted automatically when you publish.
          </p>
        ) : (
          <p className="text-muted mb-4 text-sm">
            No link in the thread — good for sharing tips, stories, or opinions. Add an affiliate link on the{' '}
            <Link to="/affiliates" className="font-semibold underline hover:text-slate-700 dark:hover:text-slate-200">
              Affiliates page
            </Link>{' '}
            when you want a product CTA.
          </p>
        )}

        <div className="panel-muted mb-5 space-y-3 p-4">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <label className="text-label flex items-center gap-1.5 text-sm font-medium">
                <ScanLine size={14} className="text-brand-500" />
                Reference image (optional)
              </label>
              <p className="text-muted mt-1 text-xs">
                Upload a product photo, screenshot, or document. AutoThreads will analyzes it and writes a thread for your niche.
              
              </p>
            </div>
            
          </div>

          {referencePreview ? (
            <div className="flex flex-wrap items-start gap-3">
              <img
                src={referencePreview}
                alt="Reference preview"
                className="max-h-36 max-w-full rounded-lg border border-slate-200 object-contain dark:border-slate-700"
              />
              <button type="button" onClick={clearReferenceImage} className="btn-secondary !py-2 !text-xs">
                <Trash2 size={14} /> Remove reference
              </button>
            </div>
          ) : (
            <label className="btn-secondary !py-2 !text-xs inline-flex cursor-pointer items-center gap-2">
              <ImagePlus size={14} />
              Upload reference image
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                className="sr-only"
                onChange={onPickReferenceImage}
              />
            </label>
          )}
        </div>

        <button
          type="button"
          onClick={runGenerate}
          disabled={generateMutation.isPending}
          className="btn-primary"
        >
          {generateMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Sparkles size={16} />}
          {generateMutation.isPending ? 'Generating...' : referenceImage ? 'Generate from image' : 'Generate content'}
        </button>
      </div>

      <h2 className="text-heading mb-4 text-lg font-semibold">Recent drafts</h2>

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
            title="No content yet"
            description="Generate your first thread using the form above."
          />
        </div>
      ) : (
        <div className="space-y-4">
          {posts.map((post) => (
            <article key={post.id} className="card-hover overflow-hidden">
              <div className="border-b border-slate-100 bg-slate-50/50 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="flex flex-wrap gap-2">
                    <span className="badge-draft">{post.category?.replace(/_/g, ' ')}</span>
                    {post.tone && <span className="badge-posted">{post.tone}</span>}
                    <span className="badge bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">Score {post.quality_score}</span>
                    {replyCount(post) != null && (
                      <span className="badge bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">{replyCount(post)} replies</span>
                    )}
                    {post.metadata?.image_generation && (
                      <span className="badge bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">From image</span>
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
                    placeholder="Keep Reply 1: / Reply 2: labels if you edit the full thread"
                  />
                ) : (
                  <>
                  <p className="whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300 sm:text-base">
                    {post.content}
                  </p>
                
                  </>
                )}

                {(post.status === 'draft' || post.status === 'approved') && (
                  <div className="panel-muted mt-4 space-y-4 p-3">
                    <div>
                      <label className="text-muted mb-2 flex items-center gap-1 text-xs font-medium">
                        <ImagePlus size={12} /> Hook image (optional)
                      </label>
                      <p className="text-muted mb-3 text-xs">
                        Attached to Reply 1 when published. JPEG or PNG, max 8 MB. Meta needs a public HTTPS URL (your ngrok tunnel).
                      </p>
                      {post.hook_image_url ? (
                        <div className="flex flex-wrap items-start gap-3">
                          <img
                            src={post.hook_image_url}
                            alt="Hook preview"
                            className="max-h-40 max-w-full rounded-lg border border-slate-200 object-contain dark:border-slate-700"
                          />
                          <button
                            type="button"
                            onClick={() => deleteHookImageMutation.mutate(post.id)}
                            disabled={deleteHookImageMutation.isPending}
                            className="btn-secondary !py-2 !text-xs"
                          >
                            {deleteHookImageMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Trash2 size={14} />}
                            Remove image
                          </button>
                        </div>
                      ) : (
                        <label className="btn-secondary !py-2 !text-xs inline-flex cursor-pointer items-center gap-2">
                          {uploadHookImageMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <ImagePlus size={14} />}
                          Upload hook image
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
                        <Link2 size={12} /> Affiliate link (optional)
                      </label>
                      <select
                        value={post.affiliate_link_id ?? ''}
                        onChange={(e) => saveAffiliate(post.id, e.target.value)}
                        className="select-field !py-2 text-sm"
                        disabled={updateMutation.isPending}
                      >
                        <option value="">None — sharing only</option>
                        {affiliates?.map((a) => (
                          <option key={a.id} value={a.id}>{a.product_name}</option>
                        ))}
                      </select>
                    </div>
                    {hasLinkPlaceholder(post) && !post.affiliate_link_id && (
                      <span className="text-muted text-xs">
                        Contains <code className="code-inline">[link]</code> — will be removed on publish unless you pick an affiliate.
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
                          Save edits
                        </button>
                        <button
                          type="button"
                          onClick={() => { setEditingId(null); setEditContent(''); }}
                          className="btn-secondary !py-2 !text-xs"
                        >
                          Cancel
                        </button>
                      </>
                    ) : (
                      <button
                        type="button"
                        onClick={() => startEdit(post)}
                        className="btn-secondary !py-2 !text-xs"
                      >
                        <Pencil size={14} /> Edit
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => approveMutation.mutate(post.id)}
                      disabled={approveMutation.isPending}
                      className="btn-success !py-2 !text-xs"
                    >
                      <Check size={14} /> Approve
                    </button>
                    <button
                      type="button"
                      onClick={() => rejectMutation.mutate(post.id)}
                      disabled={rejectMutation.isPending}
                      className="btn-danger !py-2 !text-xs"
                    >
                      <X size={14} /> Reject
                    </button>
                    <button
                      type="button"
                      onClick={() => regenerateMutation.mutate(post.id)}
                      disabled={regenerateMutation.isPending}
                      className="btn-secondary !py-2 !text-xs"
                    >
                      {regenerateMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <RefreshCw size={14} />}
                      Regenerate
                    </button>
                  </div>
                )}

                {post.status === 'scheduled' && (
                  <p className="mt-4 flex flex-wrap items-center gap-2 text-sm text-amber-800 dark:text-amber-300">
                    <Calendar size={16} />
                    Queued for automatic publish.
                    <Link to="/scheduler" className="font-semibold underline">View queue</Link>
                  </p>
                )}

                {post.status === 'approved' && (
                  <>
                  <div className="panel-muted mt-4 p-4">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2">
                        <Calendar size={16} className="text-brand-600 dark:text-brand-400" />
                        <span className="text-subheading text-sm font-semibold">Schedule publish</span>
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
                            ? 'Connect Threads in Settings first'
                            : 'Requires cron — see Scheduler page'
                        }
                      >
                        {scheduleMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Calendar size={14} />}
                        Schedule post
                      </button>
                    </div>

                    <ScheduleDateTimePicker
                      value={getScheduleAt(post.id)}
                      onChange={(v) => setScheduleAt(post.id, v)}
                      disabled={!hasThreadsAccount || scheduleMutation.isPending}
                      settings={schedulerSettings}
                    />

                    <p className="text-muted mt-3 text-xs">
                      Cron runs <code className="code-inline">publish_posts.php</code> every minute — see Scheduler for setup.
                    </p>
                  </div>

                  <div className="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    {editingId !== post.id && (
                      <button
                        type="button"
                        onClick={() => startEdit(post)}
                        className="btn-secondary !py-2 !text-xs"
                      >
                        <Pencil size={14} /> Edit
                      </button>
                    )}
                    {editingId === post.id && (
                      <>
                        <button
                          type="button"
                          onClick={() => saveEdit(post.id)}
                          disabled={updateMutation.isPending}
                          className="btn-primary !py-2 !text-xs"
                        >
                          <Save size={14} /> Save edits
                        </button>
                        <button
                          type="button"
                          onClick={() => { setEditingId(null); setEditContent(''); }}
                          className="btn-secondary !py-2 !text-xs"
                        >
                          Cancel
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
                          ? 'Connect Threads in Settings first'
                          : !canPublishChain
                            ? 'Reconnect Threads after granting threads_manage_replies'
                            : 'Publish chained replies'
                      }
                    >
                      {publishMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Send size={14} />}
                      Publish to Threads
                    </button>
                    {!hasThreadsAccount && (
                      <span className="text-muted text-xs">Connect Threads in Settings</span>
                    )}
                    {hasThreadsAccount && !canPublishChain && (
                      <span className="text-xs font-medium text-red-600 dark:text-red-400">Reconnect in Settings</span>
                    )}
                  </div>
                  </>
                )}

                {post.status === 'posted' && post.metadata?.threads_publish && (
                  <p className="mt-4 flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                    Live on Threads · {post.metadata.threads_publish.published_count} posts in chain
                  </p>
                )}
              </div>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
