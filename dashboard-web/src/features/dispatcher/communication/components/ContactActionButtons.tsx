import { Mail, Phone } from 'lucide-react';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import { mailtoHref, telHref } from '../utils/contactActions';

interface ContactActionButtonsProps {
  phone?: string | null;
  email?: string | null;
  /** When false, omit the email control (e.g. emergency phone-only). */
  showEmail?: boolean;
  callLabel?: string;
  emailLabel?: string;
  className?: string;
  compact?: boolean;
}

/** Real tel:/mailto: actions only — disabled copy explains what is missing. */
export function ContactActionButtons({
  phone,
  email,
  showEmail = true,
  callLabel = 'Call',
  emailLabel = 'Email',
  className,
  compact = false,
}: ContactActionButtonsProps) {
  const callHref = telHref(phone);
  const mailHref = mailtoHref(email);

  return (
    <div className={cn('flex flex-wrap gap-2', className)}>
      {callHref ? (
        <a
          href={callHref}
          className={cn(
            dispatcherDesignTokens.buttonPrimary,
            compact ? 'px-3 py-2 text-xs' : 'px-3 py-2.5 text-sm'
          )}
        >
          <Phone className="h-3.5 w-3.5" aria-hidden /> {callLabel}
        </a>
      ) : (
        <button
          type="button"
          disabled
          className={cn(
            dispatcherDesignTokens.buttonSecondary,
            'opacity-50',
            compact ? 'px-3 py-2 text-xs' : 'px-3 py-2.5 text-sm'
          )}
          title="Phone number unavailable on this record — use Driver Operations or another contact method"
        >
          <Phone className="h-3.5 w-3.5" aria-hidden /> {callLabel}: Unavailable
        </button>
      )}

      {showEmail ? (
        mailHref ? (
          <a
            href={mailHref}
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              compact ? 'px-3 py-2 text-xs' : 'px-3 py-2.5 text-sm'
            )}
          >
            <Mail className="h-3.5 w-3.5" aria-hidden /> {emailLabel}
          </a>
        ) : (
          <button
            type="button"
            disabled
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              'opacity-50',
              compact ? 'px-3 py-2 text-xs' : 'px-3 py-2.5 text-sm'
            )}
            title="Email unavailable on this record — use Call when a phone is present"
          >
            <Mail className="h-3.5 w-3.5" aria-hidden /> {emailLabel}: Unavailable
          </button>
        )
      ) : null}
    </div>
  );
}
