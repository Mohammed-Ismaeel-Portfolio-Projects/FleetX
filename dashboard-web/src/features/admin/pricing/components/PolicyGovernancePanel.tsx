import { useState } from 'react';
import { Archive, CheckCircle2, Pencil, Plus } from 'lucide-react';
import PolicyEditorForm from './PolicyEditorForm';
import { pricingService, PricingApiError } from '../services/pricing.service';
import type { PricingPolicy, PricingPolicyInput, PricingPolicySummary } from '../types/pricing.types';
import { buildDefaultPolicyPayload, policyToInput } from '../utils/buildDefaultPolicyPayload';
import { flattenPricingErrors, formatDateTime } from '../utils/formatPricing';

interface PolicyGovernancePanelProps {
  policies: PricingPolicySummary[];
  onPoliciesChanged: () => Promise<void>;
}

type EditorMode = 'closed' | 'create' | 'edit';

function temporalLabel(policy: PricingPolicySummary): string {
  return (policy.temporal_state ?? policy.status).replace(/_/g, ' ').toUpperCase();
}

function temporalClass(state: string): string {
  switch (state) {
    case 'current':
      return 'text-emerald-400 bg-emerald-400/10';
    case 'future':
      return 'text-sky-400 bg-sky-400/10';
    case 'expired':
    case 'archived':
      return 'text-slate-400 bg-slate-400/10';
    case 'draft':
      return 'text-amber-300 bg-amber-300/10';
    default:
      return 'text-slate-300 bg-slate-300/10';
  }
}

export default function PolicyGovernancePanel({ policies, onPoliciesChanged }: PolicyGovernancePanelProps) {
  const [editorMode, setEditorMode] = useState<EditorMode>('closed');
  const [editingPolicyId, setEditingPolicyId] = useState<number | null>(null);
  const [draft, setDraft] = useState<PricingPolicyInput>(buildDefaultPolicyPayload());
  const [isSaving, setIsSaving] = useState(false);
  const [isLifecycleBusy, setIsLifecycleBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<string[]>([]);
  const [selectedPolicyId, setSelectedPolicyId] = useState<number | null>(null);

  const selectedPolicy = policies.find((policy) => policy.id === selectedPolicyId) ?? null;

  const openCreate = () => {
    setDraft(buildDefaultPolicyPayload());
    setEditingPolicyId(null);
    setEditorMode('create');
    setError(null);
    setFieldErrors([]);
  };

  const openEdit = async (policyId: number) => {
    setError(null);
    setFieldErrors([]);
    setIsSaving(true);
    try {
      const policy: PricingPolicy = await pricingService.getPricingPolicy(policyId);
      if (policy.status !== 'draft') {
        setError('Only draft policies can be edited.');
        return;
      }
      setDraft(policyToInput(policy));
      setEditingPolicyId(policyId);
      setEditorMode('edit');
      setSelectedPolicyId(policyId);
    } catch (err) {
      setError(err instanceof PricingApiError ? err.message : 'Unable to load policy for editing.');
    } finally {
      setIsSaving(false);
    }
  };

  const closeEditor = () => {
    setEditorMode('closed');
    setEditingPolicyId(null);
    setError(null);
    setFieldErrors([]);
  };

  const handleSave = async () => {
    setError(null);
    setFieldErrors([]);
    setIsSaving(true);
    try {
      if (editorMode === 'create') {
        await pricingService.createPricingPolicy(draft);
      } else if (editorMode === 'edit' && editingPolicyId !== null) {
        await pricingService.updatePricingPolicy(editingPolicyId, draft);
      }
      closeEditor();
      await onPoliciesChanged();
    } catch (err) {
      if (err instanceof PricingApiError) {
        setError(err.message);
        setFieldErrors(flattenPricingErrors(err.errors));
      } else {
        setError(err instanceof Error ? err.message : 'Unable to save pricing policy.');
      }
    } finally {
      setIsSaving(false);
    }
  };

  const runLifecycle = async (action: 'activate' | 'archive', policyId: number) => {
    setError(null);
    setFieldErrors([]);
    setIsLifecycleBusy(true);
    try {
      if (action === 'activate') {
        await pricingService.activatePricingPolicy(policyId);
      } else {
        await pricingService.archivePricingPolicy(policyId);
      }
      await onPoliciesChanged();
    } catch (err) {
      if (err instanceof PricingApiError) {
        setError(err.message);
        setFieldErrors(flattenPricingErrors(err.errors));
      } else {
        setError(err instanceof Error ? err.message : 'Unable to update policy lifecycle.');
      }
    } finally {
      setIsLifecycleBusy(false);
    }
  };

  return (
    <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-[10px] uppercase tracking-wide text-slate-500">Policy governance</p>
          <h2 className="mt-1 text-lg font-semibold text-white">Pricing Policies</h2>
          <p className="mt-1 text-xs text-slate-500">
            Create and manage draft policies. Activation validates configuration on the backend before rates take effect.
          </p>
        </div>
        <button
          type="button"
          onClick={openCreate}
          className="inline-flex items-center gap-2 rounded-xl border border-cyan-500/30 bg-cyan-500/10 px-3 py-2 text-xs text-cyan-300 hover:bg-cyan-500/20"
        >
          <Plus className="h-3.5 w-3.5" />
          Create Draft Policy
        </button>
      </div>

      <div className="overflow-x-auto rounded-xl border border-white/5">
        <table className="min-w-full text-left text-xs text-slate-300">
          <thead className="bg-[#090D16] text-[10px] uppercase tracking-wide text-slate-500">
            <tr>
              <th className="px-3 py-2">Name</th>
              <th className="px-3 py-2">Temporal</th>
              <th className="px-3 py-2">Effective</th>
              <th className="px-3 py-2">Priority</th>
              <th className="px-3 py-2">Currency</th>
              <th className="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {policies.length === 0 && (
              <tr>
                <td colSpan={7} className="px-3 py-4 text-slate-500">No pricing policies found.</td>
              </tr>
            )}
            {policies.map((policy) => (
              <tr key={policy.id} className="border-t border-white/5">
                <td className="px-3 py-2 font-medium text-white">{policy.name}</td>
                <td className="px-3 py-2">
                  <span className={`rounded-full px-2 py-0.5 text-[10px] uppercase ${temporalClass(policy.temporal_state ?? policy.status)}`}>
                    {temporalLabel(policy)}
                  </span>
                </td>
                <td className="px-3 py-2 text-[10px] text-slate-400">
                  {formatDateTime(policy.effective_from)} → {formatDateTime(policy.effective_until)}
                </td>
                <td className="px-3 py-2">{policy.priority}</td>
                <td className="px-3 py-2">{policy.currency}</td>
                <td className="px-3 py-2">
                  <div className="flex flex-wrap gap-1">
                    <button
                      type="button"
                      onClick={() => setSelectedPolicyId(policy.id)}
                      className={`rounded-lg border px-2 py-1 text-[10px] ${selectedPolicyId === policy.id ? 'border-cyan-500/40 text-cyan-300' : 'border-white/10 text-slate-400'}`}
                    >
                      Select
                    </button>
                    {policy.status === 'draft' && (
                      <button type="button" onClick={() => void openEdit(policy.id)} className="inline-flex items-center gap-1 rounded-lg border border-white/10 px-2 py-1 text-[10px] text-slate-300">
                        <Pencil className="h-3 w-3" /> Edit
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {selectedPolicy && (
        <div className="mt-4 flex flex-wrap items-center gap-2 rounded-xl border border-white/5 bg-[#090D16] p-3">
          <span className="text-xs text-slate-400">Selected: <span className="text-white">{selectedPolicy.name}</span></span>
          {selectedPolicy.status === 'draft' && (
            <button
              type="button"
              disabled={isLifecycleBusy}
              onClick={() => void runLifecycle('activate', selectedPolicy.id)}
              className="inline-flex items-center gap-1 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 text-[10px] text-emerald-300 disabled:opacity-50"
            >
              <CheckCircle2 className="h-3 w-3" /> Activate
            </button>
          )}
          {(selectedPolicy.status === 'draft' || selectedPolicy.status === 'active') && (
            <button
              type="button"
              disabled={isLifecycleBusy}
              onClick={() => void runLifecycle('archive', selectedPolicy.id)}
              className="inline-flex items-center gap-1 rounded-lg border border-slate-500/30 bg-slate-500/10 px-2 py-1 text-[10px] text-slate-300 disabled:opacity-50"
            >
              <Archive className="h-3 w-3" /> Archive
            </button>
          )}
        </div>
      )}

      {error && editorMode === 'closed' && (
        <p className="mt-3 text-sm text-rose-400">{error}</p>
      )}
      {fieldErrors.length > 0 && editorMode === 'closed' && (
        <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-300">
          {fieldErrors.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      )}

      {editorMode !== 'closed' && (
        <div className="mt-6 border-t border-white/5 pt-6">
          <h3 className="mb-4 text-sm font-semibold text-white">
            {editorMode === 'create' ? 'Create Draft Policy' : 'Edit Draft Policy'}
          </h3>
          <PolicyEditorForm
            draft={draft}
            isSubmitting={isSaving}
            error={error}
            fieldErrors={fieldErrors}
            onChange={(updates) => setDraft((current) => ({ ...current, ...updates }))}
            onSubmit={() => void handleSave()}
            onCancel={closeEditor}
            submitLabel={editorMode === 'create' ? 'Create Draft' : 'Save Draft'}
          />
        </div>
      )}
    </div>
  );
}
