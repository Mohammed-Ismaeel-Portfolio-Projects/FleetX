interface StatusSwitchProps {
  isActive: boolean;
  isLoading: boolean;
  disabled?: boolean;
  onToggle: (nextValue: boolean) => void;
}

export default function StatusSwitch({ isActive, isLoading, disabled, onToggle }: StatusSwitchProps) {
  return (
    <button
      type="button"
      onClick={() => onToggle(!isActive)}
      disabled={disabled || isLoading}
      className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
        isActive ? 'bg-emerald-500/80' : 'bg-slate-700'
      }`}
      aria-label={isActive ? 'Deactivate vehicle' : 'Activate vehicle'}
      aria-pressed={isActive}
    >
      <span
        className={`absolute h-5 w-5 rounded-full bg-white shadow transition-transform ${
          isActive ? 'translate-x-5' : 'translate-x-0.5'
        }`}
      />
      {isLoading && (
        <span className="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border border-slate-900/50 border-t-transparent animate-spin" />
      )}
    </button>
  );
}
