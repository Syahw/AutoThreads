import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { Link2 } from 'lucide-react';

export default function Affiliates() {
  const { data: links } = useQuery({
    queryKey: ['affiliates'],
    queryFn: () => api.get('/affiliates').then((r) => r.data.data),
  });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Affiliate Links</h1>
      <div className="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
        {!links?.length ? (
          <div className="text-center py-8">
            <Link2 size={48} className="mx-auto text-gray-300 mb-3" />
            <p className="text-gray-500">No affiliate links yet</p>
          </div>
        ) : (
          <div className="space-y-3">
            {links.map((link) => (
              <div key={link.id} className="p-4 border border-gray-100 rounded-lg">
                <h3 className="font-medium text-gray-900">{link.product_name}</h3>
                <p className="text-sm text-gray-500 truncate">{link.url}</p>
                <div className="flex gap-4 mt-2 text-xs text-gray-400">
                  <span>Clicks: {link.click_count}</span>
                  <span>CTA: {link.cta_style}</span>
                  <span>Campaign: {link.campaign_tag || 'none'}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
