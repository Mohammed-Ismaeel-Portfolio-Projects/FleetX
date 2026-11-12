import { useEffect, useState } from 'react';
import { CheckCircle2, Download, MapPin, ShieldAlert, FileText, Image as ImageIcon } from 'lucide-react';
import { SignatureRenderer, type SignatureStroke } from '../../../../components/SignatureRenderer';
import { axiosClientWithInterceptors as apiClient } from '../../../../core/api';

interface DeliveryEvidenceData {
  shipment_id: string;
  receiver: {
    name: string | null;
    role: string | null;
  };
  condition: {
    status: 'good' | 'damaged' | 'partial' | 'refused' | string;
  };
  notes: string | null;
  location: {
    latitude: number | null;
    longitude: number | null;
  };
  delivered_at: string | null;
  driver: {
    id: number;
    name: string;
  } | null;
  photo: {
    available: boolean;
    url: string | null;
  };
  signature: {
    available: boolean;
    url: string | null;
    strokes: SignatureStroke[] | null;
  };
}

interface DeliveryEvidenceConsoleProps {
  shipmentId: string;
}

export function DeliveryEvidenceConsole({ shipmentId }: DeliveryEvidenceConsoleProps) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [evidence, setEvidence] = useState<DeliveryEvidenceData | null>(null);
  const [photoBlobUrl, setPhotoBlobUrl] = useState<string | null>(null);
  const [downloadingCert, setDownloadingCert] = useState(false);
  const [certNotice, setCertNotice] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;
    async function loadEvidence() {
      setLoading(true);
      setError(null);
      try {
        const { data } = await apiClient.get<{ success: boolean; data: DeliveryEvidenceData }>(
          `/shipments/${shipmentId}/delivery-proof`
        );
        if (isMounted) {
          setEvidence(data.data);
        }

        // Fetch photo blob securely if photo is available
        if (data.data?.photo?.available) {
          try {
            const blobRes = await apiClient.get(`/shipments/${shipmentId}/pod`, {
              responseType: 'blob',
            });
            const url = URL.createObjectURL(blobRes.data);
            if (isMounted) setPhotoBlobUrl(url);
          } catch {
            // photo fallback
          }
        }
      } catch (e: any) {
        if (isMounted) {
          if (e.response?.status === 404) {
            setEvidence(null);
          } else {
            setError(e.response?.data?.message || 'Unable to load delivery evidence.');
          }
        }
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    if (shipmentId) {
      void loadEvidence();
    }
    return () => {
      isMounted = false;
    };
  }, [shipmentId]);

  if (loading) {
    return (
      <section className="rounded-2xl border border-white/5 bg-[#090D16] p-5" data-testid="pod-console-loading">
        <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Delivery Evidence</h3>
        <div className="mt-4 flex h-24 items-center justify-center gap-2 text-xs text-slate-400">
          <span className="h-4 w-4 rounded-full border-2 border-primary border-t-transparent animate-spin" />
          Loading delivery evidence...
        </div>
      </section>
    );
  }

  if (error) {
    return (
      <section className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-xs text-red-200" data-testid="pod-console-error">
        <h3 className="text-[10px] font-bold uppercase tracking-widest text-red-400">Delivery Evidence Error</h3>
        <p className="mt-2">{error}</p>
      </section>
    );
  }

  if (!evidence) {
    return (
      <section className="rounded-2xl border border-white/5 bg-[#090D16] p-5" data-testid="pod-console-empty">
        <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Delivery Evidence</h3>
        <p className="mt-3 text-xs text-slate-400">No delivery evidence available yet.</p>
      </section>
    );
  }

  const openMap = () => {
    if (evidence.location.latitude != null && evidence.location.longitude != null) {
      window.open(
        `https://www.google.com/maps/search/?api=1&query=${evidence.location.latitude},${evidence.location.longitude}`,
        '_blank'
      );
    }
  };

  const handleDownloadCert = async () => {
    setDownloadingCert(true);
    setCertNotice(null);
    try {
      const res = await apiClient.get(`/shipments/${shipmentId}/delivery-proof/certificate`, {
        responseType: 'blob',
      });
      const blob = new Blob([res.data], { type: 'application/pdf' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `POD-Certificate-${shipmentId}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setCertNotice('Certificate downloaded successfully.');
    } catch {
      setCertNotice('Unable to generate certificate.');
    } finally {
      setDownloadingCert(false);
    }
  };

  const conditionColor =
    evidence.condition.status === 'good'
      ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
      : evidence.condition.status === 'damaged'
      ? 'bg-red-500/10 text-red-400 border-red-500/20'
      : 'bg-amber-500/10 text-amber-400 border-amber-500/20';

  return (
    <section className="rounded-2xl border border-white/10 bg-[#090D16] p-5 space-y-4" data-testid="pod-console">
      <div className="flex items-center justify-between border-b border-white/5 pb-3">
        <div>
          <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Delivery Evidence Console</h3>
          <p className="mt-1 flex items-center gap-1.5 text-xs text-emerald-400 font-semibold">
            <CheckCircle2 className="h-4 w-4" /> Verified Delivery
          </p>
        </div>
        <button
          type="button"
          onClick={handleDownloadCert}
          disabled={downloadingCert}
          data-testid="pod-download-cert"
          className="flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 transition-colors hover:bg-white/10 hover:text-white disabled:opacity-50"
        >
          <Download className="h-3.5 w-3.5" />
          {downloadingCert ? 'Generating certificate...' : 'Download POD Certificate'}
        </button>
      </div>

      {certNotice && (
        <div className="rounded-xl border border-blue-500/30 bg-blue-500/10 p-3 text-xs text-blue-300">
          {certNotice}
        </div>
      )}

      <div className="grid grid-cols-2 gap-3 text-xs">
        <div>
          <span className="text-slate-500 block">Delivered At</span>
          <span className="font-medium text-slate-200">{evidence.delivered_at ?? 'N/A'}</span>
        </div>
        <div>
          <span className="text-slate-500 block">Driver</span>
          <span className="font-medium text-slate-200">{evidence.driver?.name ?? 'Assigned Driver'}</span>
        </div>
        <div>
          <span className="text-slate-500 block">Receiver Name</span>
          <span className="font-medium text-slate-200">{evidence.receiver.name ?? 'Not recorded'}</span>
        </div>
        <div>
          <span className="text-slate-500 block">Receiver Role</span>
          <span className="font-medium text-slate-200">{evidence.receiver.role ?? 'Not specified'}</span>
        </div>
      </div>

      <div className="flex items-center justify-between rounded-xl border border-white/5 bg-slate-900/60 p-3">
        <span className="text-xs text-slate-400 font-medium">Cargo Condition:</span>
        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide ${conditionColor}`}>
          {evidence.condition.status}
        </span>
      </div>

      {evidence.notes && (
        <div>
          <span className="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1">Delivery Notes</span>
          <p className="rounded-xl border border-white/5 bg-slate-900/50 p-3 text-xs text-slate-300">{evidence.notes}</p>
        </div>
      )}

      {/* GPS Location Section */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <span className="text-[10px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-1">
            <MapPin className="h-3.5 w-3.5 text-primary" /> Delivery Location Snapshot
          </span>
          {evidence.location.latitude != null && (
            <button
              type="button"
              onClick={openMap}
              data-testid="pod-open-map"
              className="text-xs text-primary hover:underline font-medium"
            >
              Open Map →
            </button>
          )}
        </div>
        <div className="rounded-xl border border-white/5 bg-slate-900/50 p-3 text-xs text-slate-300">
          {evidence.location.latitude != null && evidence.location.longitude != null ? (
            <span>
              Latitude: <strong className="text-white">{evidence.location.latitude.toFixed(6)}</strong> | Longitude:{' '}
              <strong className="text-white">{evidence.location.longitude.toFixed(6)}</strong>
            </span>
          ) : (
            <span className="text-slate-500">Location unavailable snapshot</span>
          )}
        </div>
      </div>

      {/* Photo Evidence */}
      <div>
        <span className="text-[10px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-1 mb-2">
          <ImageIcon className="h-3.5 w-3.5 text-primary" /> Photo Evidence
        </span>
        {photoBlobUrl ? (
          <div className="relative group overflow-hidden rounded-xl border border-white/10 bg-slate-950">
            <img src={photoBlobUrl} alt="Delivery Evidence Photo" className="h-44 w-full object-cover" />
            <a
              href={photoBlobUrl}
              target="_blank"
              rel="noreferrer"
              className="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 transition-opacity group-hover:opacity-100 text-xs font-bold text-white"
            >
              View Full Image
            </a>
          </div>
        ) : (
          <div className="flex h-28 items-center justify-center rounded-xl border border-dashed border-white/10 bg-slate-900/40 text-xs text-slate-500">
            No photo attachment available
          </div>
        )}
      </div>

      {/* Digital Signature */}
      <div>
        <span className="text-[10px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-1 mb-2">
          <FileText className="h-3.5 w-3.5 text-primary" /> Digital Signature
        </span>
        <SignatureRenderer strokes={evidence.signature.strokes} />
      </div>
    </section>
  );
}
