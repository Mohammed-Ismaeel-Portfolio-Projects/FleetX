import { Search } from 'lucide-react';

interface SearchInputProps {
  value: string;
  onChange: (value: string) => void;
}

export default function SearchInput({ value, onChange }: SearchInputProps) {
  return (
    <div className="relative flex-1">
      <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
      <input
        type="search"
        placeholder="Search by name, code, or email..."
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-xl border border-white/5 bg-[#0A0E17] py-2.5 pl-10 pr-4 text-sm text-white outline-none transition-all placeholder:text-slate-500 focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20"
        aria-label="Search companies"
      />
    </div>
  );
}
