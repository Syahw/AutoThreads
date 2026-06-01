import { Settings as SettingsIcon } from 'lucide-react';

export default function Settings() {
  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Settings</h1>
      <div className="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
        <div className="text-center py-8">
          <SettingsIcon size={48} className="mx-auto text-gray-300 mb-3" />
          <p className="text-gray-500">Settings panel</p>
          <p className="text-sm text-gray-400 mt-1">Manage API keys, scheduling preferences, and account settings</p>
        </div>
      </div>
    </div>
  );
}
