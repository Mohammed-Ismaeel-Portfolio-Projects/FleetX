import Navbar from '../../components/common/Navbar';
import ScrollToTopButton from '../../components/common/ScrollToTopButton';
import Hero from './components/Hero';
import ProblemSection from './components/ProblemSection';
import SolutionSection from './components/SolutionSection';
import HowItWorksSection from './components/HowItWorksSection';
import FeatureSuite from './components/FeatureSuite';
import RoleSuite from './components/RoleSuite';
import CommandCenter from './components/CommandCenter';
import TechSuite from './components/TechSuite';
import MetricsSuite from './components/MetricsSuite';
import Contact from './components/Contact';
import Footer from '../../components/common/Footer';

export default function LandingPage() {
  return (
    <div className="relative min-h-screen bg-[#121620] w-full flex flex-col">
      {/* Navigation Header */}
      <Navbar />

      {/* Main sections */}
      <main className="flex-grow w-full">
        <Hero />
        <ProblemSection />
        <SolutionSection />
        <HowItWorksSection />
        <FeatureSuite />
        <RoleSuite />
        <CommandCenter />
        <TechSuite />
        <MetricsSuite />
        <Contact />
      </main>

      {/* Footer Section */}
      <Footer />
      
      {/* Scroll to Top Button */}
      <ScrollToTopButton />
    </div>
  );
}
