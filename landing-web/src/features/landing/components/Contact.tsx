import { motion } from 'framer-motion';

export default function Contact() {
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
  };

  return (
    <section id="contact" className="py-24 bg-[#f8fafc] w-full overflow-hidden border-t border-slate-100 relative">
      {/* Decorative background glow */}
      <div className="absolute top-1/3 left-10 w-[300px] h-[300px] bg-blue-100/10 rounded-full blur-[80px] pointer-events-none" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10">
        
        {/* Trust Elements */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true, margin: "-50px" }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
        >
          <p className="text-slate-500 text-sm font-medium mb-6">Trusted by logistics companies worldwide</p>
          <div className="flex flex-wrap justify-center gap-8 items-center">
            <div className="flex items-center gap-2 text-slate-600 text-xs font-semibold">
              <div className="w-2 h-2 rounded-full bg-emerald-500" />
              <span>Enterprise-grade security</span>
            </div>
            <div className="flex items-center gap-2 text-slate-600 text-xs font-semibold">
              <div className="w-2 h-2 rounded-full bg-emerald-500" />
              <span>Secure & scalable architecture</span>
            </div>
            <div className="flex items-center gap-2 text-slate-600 text-xs font-semibold">
              <div className="w-2 h-2 rounded-full bg-emerald-500" />
              <span>Built for industrial fleet operations</span>
            </div>
          </div>
        </motion.div>

        {/* CTA Card Banner */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true, margin: "-50px" }}
          transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
          className="bg-white border border-slate-200 rounded-3xl p-10 max-w-4xl mx-auto shadow-sm mb-20 text-center flex flex-col items-center select-none"
        >
          <h2 className="text-3xl md:text-[34px] font-extrabold text-slate-900 font-sans tracking-tight mb-4 leading-tight">
            Ready to Transform Your Logistics?
          </h2>
          <p className="text-slate-500 text-[14px] md:text-[15px] font-normal leading-relaxed mb-8 max-w-xl">
            Join leading companies using FleetX to optimize operations and reduce costs.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
            <a
              href="#contact"
              className="inline-flex items-center justify-center px-8 py-4 rounded-xl text-sm font-bold text-white bg-[#0250C5] hover:bg-[#0244a5] transition-colors shadow-md hover:shadow-lg select-none cursor-pointer text-center"
            >
              Request Demo
            </a>
          </div>
        </motion.div>

        {/* Contact Form Card matching the reference layout */}
        <motion.div 
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true, margin: "-100px" }}
          transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1], delay: 0.1 }}
          className="bg-white border border-slate-200 rounded-3xl p-8 max-w-2xl mx-auto shadow-lg relative mb-12"
        >
          {/* Subtag */}
          <span className="text-[#0250C5] text-[10px] font-extrabold uppercase tracking-[0.2em] mb-3 block select-none font-sans">
            Direct Support
          </span>

          <h3 className="text-2xl font-extrabold text-slate-900 tracking-tight leading-tight mb-3 font-sans">
            Request Your Demo
          </h3>
          <p className="text-slate-500 text-sm font-normal leading-relaxed mb-8 max-w-md mx-auto">
            Our team will help you optimize your logistics operations.
          </p>

          <form onSubmit={handleSubmit} className="flex flex-col w-full text-left">
            
            {/* Name & Email Field Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
              <div className="flex flex-col">
                <label htmlFor="name" className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 font-sans select-none">
                  Name
                </label>
                <input 
                  type="text" 
                  id="name" 
                  placeholder="John Doe" 
                  className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0250C5]/10 focus:border-[#0250C5] text-sm transition-all duration-200"
                  required
                />
              </div>

              <div className="flex flex-col">
                <label htmlFor="email" className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 font-sans select-none">
                  Email Address
                </label>
                <input 
                  type="email" 
                  id="email" 
                  placeholder="john@company.com" 
                  className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0250C5]/10 focus:border-[#0250C5] text-sm transition-all duration-200"
                  required
                />
              </div>
            </div>

            {/* Company Field */}
            <div className="flex flex-col mb-5">
              <label htmlFor="company" className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 font-sans select-none">
                Company
              </label>
              <input 
                type="text" 
                id="company" 
                placeholder="Your company name" 
                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0250C5]/10 focus:border-[#0250C5] text-sm transition-all duration-200"
              />
            </div>

            {/* Message Field */}
            <div className="flex flex-col mb-8">
              <label htmlFor="message" className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 font-sans select-none">
                Message
              </label>
              <textarea 
                id="message" 
                placeholder="Tell us about your fleet needs..." 
                className="w-full h-32 px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0250C5]/10 focus:border-[#0250C5] text-sm transition-all duration-200 resize-none"
                required
              />
            </div>

            {/* Submit Button */}
            <button 
              type="submit"
              className="w-full py-3.5 bg-[#0250C5] text-white font-bold rounded-xl shadow-md hover:bg-[#0244a5] hover:shadow-lg transition-all duration-200 active:scale-[0.99] select-none cursor-pointer text-center text-sm font-sans"
            >
              Request Demo
            </button>

          </form>
        </motion.div>

      </div>
    </section>
  );
}
