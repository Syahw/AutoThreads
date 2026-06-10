import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { Link2, Loader2, CheckCircle2, AlertCircle, Unplug, Shield } from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import ThemeToggle from '../components/ThemeToggle';
import SchedulePresetsEditor from '../components/settings/SchedulePresetsEditor';
import { formatDateTime } from '../utils/date';
import { useTranslation } from '../i18n';

export default function Settings() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const [banner, setBanner] = useState(null);
  const [connecting, setConnecting] = useState(false);

  const { data: accounts, isLoading } = useQuery({
    queryKey: ['threads-accounts'],
    queryFn: () => api.get('/threads/accounts').then((r) => r.data.data),
  });

  const disconnectMutation = useMutation({
    mutationFn: (id) => api.delete(`/threads/accounts/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['threads-accounts'] }),
  });

  useEffect(() => {
    const status = searchParams.get('threads');
    if (!status) return;

    if (status === 'connected' || status === 'connected_missing_scopes') {
      const username = searchParams.get('username');
      if (status === 'connected_missing_scopes') {
        setBanner({
          type: 'error',
          text: searchParams.get('message') || t('settings.missingScopes'),
        });
      } else {
        setBanner({
          type: 'success',
          text: username
            ? t('settings.connectedUser', { username })
            : t('settings.connectedGeneric'),
        });
      }
      queryClient.invalidateQueries({ queryKey: ['threads-accounts'] });
    } else if (status === 'error') {
      setBanner({
        type: 'error',
        text: searchParams.get('message') || t('settings.connectionFailed'),
      });
    }

    setSearchParams({}, { replace: true });
  }, [searchParams, setSearchParams, queryClient, t]);

  const handleConnect = async () => {
    setConnecting(true);
    setBanner(null);
    try {
      const { data } = await api.get('/threads/connect');
      const authUrl = data?.data?.auth_url;
      if (!authUrl) throw new Error('No authorization URL returned');
      window.location.href = authUrl;
    } catch (err) {
      setBanner({
        type: 'error',
        text: err.response?.data?.message || err.message || t('settings.connectStartFailed'),
      });
      setConnecting(false);
    }
  };

  return (
    <div>
      <PageHeader
        title={t('settings.title')}
        description={t('settings.description')}
      />

      {banner && (
        <div
          className={`mb-6 flex items-start gap-3 ${
            banner.type === 'success' ? 'alert-success' : 'alert-error'
          }`}
        >
          {banner.type === 'success' ? (
            <CheckCircle2 size={20} className="shrink-0" />
          ) : (
            <AlertCircle size={20} className="shrink-0" />
          )}
          <span>{banner.text}</span>
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="card p-6">
          <h2 className="text-heading mb-1 text-lg font-semibold">{t('theme.appearance')}</h2>
          <p className="text-muted mb-4 text-sm">{t('theme.appearanceDesc')}</p>
          <ThemeToggle />
        </div>

        <div className="card lg:col-span-2 p-6">
          <div className="mb-5 flex items-center gap-3">
            <div className="icon-box">
              <Link2 size={20} />
            </div>
            <div>
              <h2 className="text-heading text-lg font-semibold">{t('settings.threadsAccount')}</h2>
              <p className="text-muted text-sm">{t('settings.threadsRequired')}</p>
            </div>
          </div>

          {isLoading ? (
            <p className="text-muted flex items-center gap-2 text-sm">
              <Loader2 size={16} className="animate-spin" /> {t('settings.loadingAccounts')}
            </p>
          ) : accounts?.length > 0 ? (
            <div className="mb-5 space-y-3">
              {accounts.map((account) => (
                <div
                  key={account.id}
                  className="panel-muted p-4"
                >
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-heading font-semibold">@{account.username}</p>
                      <p className="text-muted mt-1 text-xs">
                        {account.connected_at
                          ? t('settings.connectedAt', { date: formatDateTime(account.connected_at) })
                          : '—'}
                      </p>
                      <p
                        className={`mt-2 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                          account.can_publish_reply_chain
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                            : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300'
                        }`}
                      >
                        {account.can_publish_reply_chain ? t('settings.replyChainsReady') : t('settings.missingManageReplies')}
                      </p>
                      {account.token_scopes?.length > 0 && (
                        <p className="mt-2 break-words text-xs text-slate-400 dark:text-slate-500">
                          {account.token_scopes.join(' · ')}
                        </p>
                      )}
                    </div>
                    <button
                      type="button"
                      onClick={() => disconnectMutation.mutate(account.id)}
                      disabled={disconnectMutation.isPending}
                      className="btn-danger !py-2 !text-xs"
                    >
                      <Unplug size={14} /> {t('settings.disconnect')}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-muted mb-5 text-sm">{t('settings.noThreadsConnected')}</p>
          )}

          <button type="button" onClick={handleConnect} disabled={connecting} className="btn-primary">
            {connecting ? <Loader2 size={16} className="animate-spin" /> : <Link2 size={16} />}
            {connecting
              ? t('settings.redirectingMeta')
              : accounts?.length
                ? t('settings.reconnectThreads')
                : t('settings.connectThreads')}
          </button>
        </div>

        <div className="card p-6">
          <div className="icon-box-muted mb-4">
            <Shield size={20} />
          </div>
          <h3 className="text-heading font-semibold">{t('settings.devHttps')}</h3>
          <p className="text-muted mt-2 text-sm">
            {t('settings.devHttpsBody')}
          </p>
        </div>

        <SchedulePresetsEditor />
      </div>
    </div>
  );
}
