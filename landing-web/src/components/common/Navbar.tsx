import { useState, useEffect } from 'react';
import { Menu, X } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { cn } from '../../lib/utils';
import logoImg from '../../assets/logo/logo.png';

interface NavItem {
  label: string;
  href: string;
}

const navItems: NavItem[] = [
  { label: 'Features', href: '#features' },
  { label: 'Roles', href: '#role-suite' },
  { label: 'Live Tracking', href: '#live-tracking' },
  { label: 'AI Tech', href: '#ai-tech' },
  { label: 'Performance', href: '#performance' },
  { label: 'Contact', href: '#contact' },
];

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [activeSection, setActiveSection] = useState('');

  // Handle transparent to blurred scroll effect
  useEffect(() => {
    const handleScroll = () => {
      if (window.scrollY > 20) {
        setIsScrolled(true);
      } else {
        setIsScrolled(false);
      }
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Scroll spy for active section highlighting
  useEffect(() => {
    const sections = navItems.map(item => item.href.substring(1));
    
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            setActiveSection(entry.target.id);
          }
        });
      },
      { threshold: 0.3 }
    );

    sections.forEach(section => {
      const element = document.getElementById(section);
      if (element) observer.observe(element);
    });

    return () => observer.disconnect();
  }, []);

  // Smooth scroll handler
  const handleSmoothScroll = (e: React.MouseEvent<HTMLAnchorElement>, href: string) => {
    e.preventDefault();
    const element = document.querySelector(href);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
      setIsOpen(false);
    }
  };

  return (
    <header
      className={cn(
        'fixed top-0 left-0 right-0 z-50 transition-all duration-350 px-6 md:px-16 py-5',
        isScrolled 
          ? 'bg-[#121620]/80 backdrop-blur-md border-b border-white/5 shadow-lg py-4' 
          : 'bg-transparent py-6'
      )}
    >
      <div className="max-w-7xl mx-auto flex items-center justify-between">
        {/* Logo matching the reference */}
        <a href="/" className="flex items-center gap-2.5 group">
          <img 
            src={logoImg} 
            alt="FleetX Logo" 
            className="w-8 h-8 rounded-lg object-cover border border-white/10 transition-transform duration-300 group-hover:scale-105"
          />
          <span className="font-bold text-xl tracking-tight text-white font-sans">
            Fleet<span className="text-[#0250C5]">X</span>
          </span>
        </a>

        {/* Center Links matching the reference */}
        <nav className="hidden md:flex items-center gap-9">
          {navItems.map((item) => (
            <a
              key={item.label}
              href={item.href}
              onClick={(e) => handleSmoothScroll(e, item.href)}
              className={cn(
                'text-[14px] font-medium transition-colors duration-200',
                activeSection === item.href.substring(1) 
                  ? 'text-white' 
                  : 'text-slate-400 hover:text-white'
              )}
            >
              {item.label}
            </a>
          ))}
        </nav>

        {/* Right buttons matching the reference */}
        <div className="hidden md:flex items-center">
          <a
            href="#contact"
            onClick={(e) => handleSmoothScroll(e, '#contact')}
            className="inline-flex items-center justify-center px-5 py-2 rounded-full text-[13px] font-bold text-white bg-[#0250C5] hover:bg-[#0244a5] hover:shadow-lg hover:shadow-blue-900/30 transition-all duration-200 active:scale-98 cursor-pointer select-none"
          >
            Contact Us
          </a>
        </div>

        {/* Mobile menu toggle */}
        <div className="md:hidden flex items-center">
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="p-2 text-slate-400 hover:text-white transition-colors focus:outline-none cursor-pointer"
            aria-label="Toggle menu"
          >
            {isOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>
      </div>

      {/* Mobile Drawer Menu */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.2 }}
            className="absolute top-full left-0 right-0 bg-[#121620]/95 backdrop-blur-lg border-b border-white/5 px-6 py-6 shadow-2xl flex flex-col gap-6 md:hidden"
          >
            <nav className="flex flex-col gap-4">
              {navItems.map((item) => (
                <a
                  key={item.label}
                  href={item.href}
                  onClick={(e) => handleSmoothScroll(e, item.href)}
                  className={cn(
                    'text-base font-medium transition-colors py-1',
                    activeSection === item.href.substring(1) 
                      ? 'text-white' 
                      : 'text-slate-300 hover:text-white'
                  )}
                >
                  {item.label}
                </a>
              ))}
            </nav>

            <hr className="border-white/5" />

            <a
              href="#contact"
              onClick={(e) => handleSmoothScroll(e, '#contact')}
              className="flex items-center justify-center py-2.5 text-base font-bold text-white bg-[#0250C5] hover:bg-[#0244a5] rounded-lg transition-colors"
            >
              Contact Us
            </a>
          </motion.div>
        )}
      </AnimatePresence>
    </header>
  );
}
