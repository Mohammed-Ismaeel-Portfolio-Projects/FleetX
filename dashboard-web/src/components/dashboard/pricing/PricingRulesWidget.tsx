import { useState } from 'react';
import { Sliders, Check } from 'lucide-react';

export default function PricingRulesWidget() {
  // Sliders state starting with reference image values
  const [baseRate, setBaseRate] = useState(1.45);
  const [weightMultiplier, setWeightMultiplier] = useState(1.2);
  const [urgencyFactor, setUrgencyFactor] = useState(1.8);
  const [isSaved, setIsSaved] = useState(false);

  // Calculate dynamic quote matching the visual reference (500km, 2t)
  // 500 * 1.45 * 1.2 * 1.8 * 0.9835 = 1540.20
  const distance = 500;
  const quote = distance * baseRate * weightMultiplier * urgencyFactor * 0.9835;

  const handleSave = () => {
    setIsSaved(true);
    setTimeout(() => setIsSaved(false), 2000);
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between min-h-[440px] shadow-md select-none text-left">
      
      {/* Header Title */}
      <div className="flex justify-between items-center mb-6 flex-shrink-0">
        <div className="flex items-center gap-2">
          <Sliders className="w-4 h-4 text-[#0250C5]" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Global Rules
          </h3>
        </div>
        
        {/* Sliders settings button */}
        <button className="text-slate-500 hover:text-white transition-colors cursor-pointer">
          <Sliders className="w-3.5 h-3.5 rotate-90" />
        </button>
      </div>

      {/* Sliders Container Stack */}
      <div className="flex flex-col gap-6 flex-grow">
        
        {/* 1. Base Rate */}
        <div className="flex flex-col">
          <div className="flex justify-between items-center text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 font-sans">
            <span>Base Rate per KM</span>
            <span className="text-[#00E5FF] font-mono">${baseRate.toFixed(2)}</span>
          </div>
          <input 
            type="range" 
            min="0.50" 
            max="4.00" 
            step="0.05"
            value={baseRate}
            onChange={(e) => setBaseRate(parseFloat(e.target.value))}
            className="w-full h-1 bg-white/5 rounded-lg appearance-none cursor-pointer accent-[#0250C5] focus:outline-none"
            aria-label="Base Rate per KM"
          />
        </div>

        {/* 2. Weight Multiplier */}
        <div className="flex flex-col">
          <div className="flex justify-between items-center text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 font-sans">
            <span>Weight Multiplier</span>
            <span className="text-[#00E5FF] font-mono">{weightMultiplier.toFixed(1)}x</span>
          </div>
          <input 
            type="range" 
            min="1.0" 
            max="3.0" 
            step="0.1"
            value={weightMultiplier}
            onChange={(e) => setWeightMultiplier(parseFloat(e.target.value))}
            className="w-full h-1 bg-white/5 rounded-lg appearance-none cursor-pointer accent-[#0250C5] focus:outline-none"
            aria-label="Weight Multiplier"
          />
        </div>

        {/* 3. Urgency Factor */}
        <div className="flex flex-col">
          <div className="flex justify-between items-center text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 font-sans">
            <span>Urgency Factor</span>
            <span className="text-[#00E5FF] font-mono">{urgencyFactor.toFixed(1)}x</span>
          </div>
          <input 
            type="range" 
            min="1.0" 
            max="3.0" 
            step="0.1"
            value={urgencyFactor}
            onChange={(e) => setUrgencyFactor(parseFloat(e.target.value))}
            className="w-full h-1 bg-white/5 rounded-lg appearance-none cursor-pointer accent-[#0250C5] focus:outline-none"
            aria-label="Urgency Factor"
          />
        </div>

        {/* Real-time preview Quote Box matching reference */}
        <div className="bg-[#090D16] border border-white/5 rounded-xl p-4.5 flex flex-col gap-1 mt-auto">
          <span className="text-[8.5px] font-bold text-slate-500 uppercase tracking-widest font-sans select-none text-center">
            Real-Time Preview
          </span>
          <div className="flex justify-between items-center gap-4 mt-2">
            <div className="text-[10px] text-slate-400 font-sans max-w-[100px] leading-tight">
              Est. Quote (500km, 2t)
            </div>
            <span className="text-lg font-extrabold text-white font-sans leading-none">
              ${quote.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </span>
          </div>
        </div>

        {/* Save button */}
        <button
          onClick={handleSave}
          className="w-full py-3 bg-gradient-to-r from-[#0250C5] to-[#00A5D6] hover:brightness-105 active:scale-[0.98] transition-all duration-200 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2 cursor-pointer shadow-md select-none"
        >
          {isSaved ? (
            <>
              <Check className="w-4 h-4 text-emerald-400" />
              <span>Rules Saved Successfully</span>
            </>
          ) : (
            <span>Save & Apply Rules</span>
          )}
        </button>

      </div>

    </div>
  );
}
