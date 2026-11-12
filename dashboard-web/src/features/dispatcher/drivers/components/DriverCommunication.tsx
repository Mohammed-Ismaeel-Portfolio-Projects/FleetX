import { Mail, MessageSquare, Phone } from 'lucide-react';
import type { Driver } from '../types/driver-operations.types';
import {
  displayOrUnavailable,
  mailtoHref,
  telHref,
} from '../../communication/utils/contactActions';

interface DriverCommunicationProps {
  driver?: Driver | null;
}

/** Driver contact actions from Drivers SSOT — no in-app messaging. */
export default function DriverCommunication({ driver = null }: DriverCommunicationProps) {
  const callHref = telHref(driver?.phone);
  const mailHref = mailtoHref(driver?.email);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <MessageSquare className="w-4 h-4 text-[#00E5FF]" />
          Driver Communication
        </h2>
      </div>
      <div className="flex-1 flex flex-col justify-center gap-4 p-6">
        {!driver ? (
          <p className="text-sm text-slate-400 text-center">
            Select a driver to use verified phone and email contacts.
          </p>
        ) : (
          <>
            <div className="space-y-1 text-sm text-slate-300">
              <p>Phone: {displayOrUnavailable(driver.phone)}</p>
              <p>Email: {displayOrUnavailable(driver.email)}</p>
            </div>
            <div className="flex flex-wrap gap-2">
              {callHref ? (
                <a
                  href={callHref}
                  className="inline-flex items-center gap-2 rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-3 py-2 text-xs font-semibold text-cyan-200"
                >
                  <Phone className="h-3.5 w-3.5" /> Call
                </a>
              ) : (
                <span className="inline-flex items-center gap-2 rounded-xl border border-white/10 px-3 py-2 text-xs text-slate-500">
                  Call unavailable
                </span>
              )}
              {mailHref ? (
                <a
                  href={mailHref}
                  className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200"
                >
                  <Mail className="h-3.5 w-3.5" /> Email
                </a>
              ) : (
                <span className="inline-flex items-center gap-2 rounded-xl border border-white/10 px-3 py-2 text-xs text-slate-500">
                  Email unavailable
                </span>
              )}
            </div>
            <p className="text-xs text-slate-500 text-center">
              In-app messaging is not supported by the backend.
            </p>
          </>
        )}
      </div>
    </div>
  );
}
