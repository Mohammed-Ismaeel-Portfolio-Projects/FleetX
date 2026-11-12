import { Globe, MessageSquare, Mail } from 'lucide-react';
import logoImg from '../../assets/logo/logo.png';

export default function Footer() {
  return (
    <footer className="bg-white border-t border-slate-100 pt-16 pb-8 w-full overflow-hidden relative">
      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full">
        
        {/* Main Grid Structure */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-10 md:gap-8 mb-12">
          
          {/* Logo & Slogan Column */}
          <div className="md:col-span-5 flex flex-col items-start text-left">
            <a href="/" className="flex items-center gap-2.5 group select-none">
              <img 
                src={logoImg} 
                alt="FleetX Logo" 
                className="w-8 h-8 rounded-lg object-cover border border-slate-100 transition-transform duration-300 group-hover:scale-105"
              />
              <span className="font-bold text-xl tracking-tight text-slate-800 font-sans">
                Fleet<span className="text-[#0250C5]">X</span>
              </span>
            </a>
            <p className="text-slate-500 text-sm font-normal leading-relaxed max-w-[280px] mt-4 mb-6 font-sans">
              The intelligence layer for global shipping and supply chain management.
            </p>
            {/* Social icons matching layout */}
            <div className="flex items-center gap-4">
              <a href="#web" className="text-slate-400 hover:text-[#0250C5] transition-colors" aria-label="Website">
                <Globe className="w-5 h-5" />
              </a>
              <a href="#chat" className="text-slate-400 hover:text-[#0250C5] transition-colors" aria-label="Support chat">
                <MessageSquare className="w-5 h-5" />
              </a>
              <a href="#mail" className="text-slate-400 hover:text-[#0250C5] transition-colors" aria-label="Email support">
                <Mail className="w-5 h-5" />
              </a>
            </div>
          </div>

          {/* Platform Links Column */}
          <div className="md:col-span-2.5 flex flex-col items-start text-left">
            <h4 className="text-[10px] font-bold text-slate-400 tracking-widest uppercase mb-4 font-sans select-none">
              Platform
            </h4>
            <div className="flex flex-col gap-3">
              <a href="#features" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">Features</a>
              <a href="#pricing" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">Pricing</a>
              <a href="#api" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">API Docs</a>
              <a href="#demo" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">Live Demo</a>
            </div>
          </div>

          {/* Company Links Column */}
          <div className="md:col-span-2 flex flex-col items-start text-left">
            <h4 className="text-[10px] font-bold text-slate-400 tracking-widest uppercase mb-4 font-sans select-none">
              Company
            </h4>
            <div className="flex flex-col gap-3">
              <a href="#about" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">About Us</a>
              <a href="#careers" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">Careers</a>
              <a href="#support" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">Contact Support</a>
            </div>
          </div>

          {/* Legal Links Column */}
          <div className="md:col-span-2 flex flex-col items-start text-left">
            <h4 className="text-[10px] font-bold text-slate-400 tracking-widest uppercase mb-4 font-sans select-none">
              Legal
            </h4>
            <div className="flex flex-col gap-3">
              <a href="#privacy" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">Privacy Policy</a>
              <a href="#terms" className="text-slate-500 hover:text-slate-800 font-medium text-[13px] transition-colors">Terms of Service</a>
            </div>
          </div>

        </div>

        {/* Separator line and Copyright */}
        <div className="pt-8 border-t border-slate-100 text-left">
          <p className="text-slate-400 text-[11px] font-medium font-sans select-none">
            &copy; 2026 FleetX Logistics. All rights reserved.
          </p>
        </div>

      </div>
    </footer>
  );
}
