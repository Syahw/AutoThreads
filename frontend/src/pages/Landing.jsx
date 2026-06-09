import { useState, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  Zap, Check, ArrowRight, Play, Pause, Star, Sparkles,
  Bot, CalendarClock, BarChart3, Link2, Image, Users,
  ChevronDown, Menu, X, BadgeCheck, Flame, Crown,
} from 'lucide-react';

// ─── Nav ────────────────────────────────────────────────────────────────────

function Navbar() {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200/60">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-brand-gradient flex items-center justify-center shadow-glow">
              <Zap className="w-4 h-4 text-white" strokeWidth={2.5} />
            </div>
            <span className="text-lg font-bold text-slate-900 tracking-tight">AutoThreads</span>
          </div>

          {/* Desktop links */}
          <div className="hidden md:flex items-center gap-8">
            <a href="#features" className="text-sm text-slate-600 hover:text-brand-600 transition-colors">Features</a>
            <a href="#how-it-works" className="text-sm text-slate-600 hover:text-brand-600 transition-colors">How it Works</a>
            <a href="#pricing" className="text-sm text-slate-600 hover:text-brand-600 transition-colors">Pricing</a>
          </div>

          {/* CTA */}
          <div className="hidden md:flex items-center gap-3">
            <Link to="/login" className="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">
              Log in
            </Link>
            <Link
              to="/login"
              className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-brand-gradient text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity"
            >
              Get Started Free <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          {/* Mobile toggle */}
          <button
            className="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100"
            onClick={() => setMobileOpen((v) => !v)}
          >
            {mobileOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </button>
        </div>
      </div>

      {/* Mobile menu */}
      {mobileOpen && (
        <div className="md:hidden border-t border-slate-200 bg-white px-4 py-4 flex flex-col gap-4">
          <a href="#features" className="text-sm text-slate-700" onClick={() => setMobileOpen(false)}>Features</a>
          <a href="#how-it-works" className="text-sm text-slate-700" onClick={() => setMobileOpen(false)}>How it Works</a>
          <a href="#pricing" className="text-sm text-slate-700" onClick={() => setMobileOpen(false)}>Pricing</a>
          <hr className="border-slate-200" />
          <Link to="/login" className="text-sm font-semibold text-brand-600">Log in → </Link>
        </div>
      )}
    </nav>
  );
}

// ─── Hero ────────────────────────────────────────────────────────────────────

function Hero() {
  return (
    <section className="relative min-h-screen flex items-center pt-16 overflow-hidden bg-white">
      {/* Background mesh */}
      <div className="absolute inset-0 bg-mesh pointer-events-none" />
      <div className="absolute top-20 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full bg-brand-500/5 blur-3xl pointer-events-none" />

      {/* Floating badges */}
      <div className="absolute top-32 left-8 md:left-20 hidden md:flex items-center gap-2 px-3 py-1.5 bg-white rounded-full shadow-card border border-slate-100 text-xs font-medium text-slate-700 animate-bounce-slow">
        <Sparkles className="w-3.5 h-3.5 text-amber-500" /> AI-Powered Content
      </div>
      <div className="absolute top-48 right-8 md:right-20 hidden md:flex items-center gap-2 px-3 py-1.5 bg-white rounded-full shadow-card border border-slate-100 text-xs font-medium text-slate-700">
        <BadgeCheck className="w-3.5 h-3.5 text-emerald-500" /> Sounds 100% Human
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-24 md:py-32">
        <div className="max-w-3xl mx-auto text-center">
          {/* Badge */}
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-semibold mb-6">
            <Flame className="w-3.5 h-3.5 text-brand-500" />
            Automate Your Threads Content in Bahasa Malaysia
          </div>

          {/* Headline */}
          <h1 className="text-4xl sm:text-5xl md:text-6xl font-extrabold text-slate-900 leading-tight tracking-tight mb-6">
            AI Content That{' '}
            <span className="relative inline-block">
              <span className="relative z-10 bg-brand-gradient bg-clip-text text-transparent">
                Sounds Human
              </span>
              <span className="absolute bottom-1 left-0 right-0 h-3 bg-brand-100 rounded-full -z-10" />
            </span>
            <br />for Meta Threads
          </h1>

          <p className="text-lg text-slate-500 max-w-xl mx-auto mb-10 leading-relaxed">
            Generate, schedule, and publish affiliate content on Threads — automatically.
            Built for Malaysian creators who want to grow without the grind.
          </p>

          {/* CTAs */}
          <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
            <Link
              to="/login"
              className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-brand-gradient text-white font-semibold shadow-glow hover:opacity-90 transition-all hover:scale-105"
            >
              Start for Free <ArrowRight className="w-4 h-4" />
            </Link>
            <a
              href="#demo"
              className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold hover:border-brand-300 hover:text-brand-600 transition-all"
            >
              <Play className="w-4 h-4 text-brand-500" /> Watch Demo
            </a>
          </div>

          {/* Social proof */}
          <div className="mt-12 flex flex-wrap items-center justify-center gap-6 text-sm text-slate-500">
            <div className="flex items-center gap-1.5">
              <div className="flex">
                {[...Array(5)].map((_, i) => (
                  <Star key={i} className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                ))}
              </div>
              <span>Loved by creators</span>
            </div>
            <span className="w-px h-4 bg-slate-200" />
            <span><strong className="text-slate-900">No credit card</strong> required</span>
            <span className="w-px h-4 bg-slate-200" />
            <span><strong className="text-slate-900">Cancel</strong> anytime</span>
          </div>
        </div>

        {/* Scroll cue */}
        <div className="flex justify-center mt-16">
          <a href="#features" className="flex flex-col items-center gap-1 text-slate-400 hover:text-brand-500 transition-colors">
            <span className="text-xs">Explore</span>
            <ChevronDown className="w-4 h-4 animate-bounce" />
          </a>
        </div>
      </div>
    </section>
  );
}

// ─── Stats ───────────────────────────────────────────────────────────────────

function Stats() {
  const items = [
    { value: '10x', label: 'Faster content creation' },
    { value: '500+', label: 'Posts scheduled daily' },
    { value: '9', label: 'Content categories' },
    { value: '100%', label: 'Bahasa Malaysia native' },
  ];

  return (
    <section className="bg-slate-900 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
          {items.map((s) => (
            <div key={s.label} className="text-center">
              <div className="text-3xl font-extrabold bg-brand-gradient bg-clip-text text-transparent mb-1">
                {s.value}
              </div>
              <div className="text-sm text-slate-400">{s.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ─── Features ────────────────────────────────────────────────────────────────

const features = [
  {
    icon: <Bot className="w-5 h-5" />,
    color: 'brand',
    title: 'AI Content Generation',
    desc: 'Generate engaging affiliate threads with GPT-4 using 9 content categories and 8 tones — tuned for Bahasa Malaysia.',
  },
  {
    icon: <Sparkles className="w-5 h-5" />,
    color: 'violet',
    title: 'Humanization Pipeline',
    desc: 'Remove AI tell-tale patterns automatically. Every post passes quality scoring to sound naturally human.',
  },
  {
    icon: <CalendarClock className="w-5 h-5" />,
    color: 'blue',
    title: 'Smart Scheduler',
    desc: 'Set posting windows, auto-schedule your queue, and let the cron worker publish while you sleep.',
  },
  {
    icon: <Link2 className="w-5 h-5" />,
    color: 'emerald',
    title: 'Affiliate Integration',
    desc: 'Add product links with customizable CTA styles. Links are auto-injected at publish time — zero manual work.',
  },
  {
    icon: <Image className="w-5 h-5" />,
    color: 'amber',
    title: 'Hook Image Support',
    desc: 'Attach eye-catching images to your first reply. Upload reference images for image-informed AI generation.',
  },
  {
    icon: <BarChart3 className="w-5 h-5" />,
    color: 'rose',
    title: 'Analytics Dashboard',
    desc: 'Track impressions, engagement trends, best posting times, and top-performing hooks in one view.',
  },
];

const colorMap = {
  brand: 'bg-brand-50 text-brand-600',
  violet: 'bg-violet-50 text-violet-600',
  blue: 'bg-blue-50 text-blue-600',
  emerald: 'bg-emerald-50 text-emerald-600',
  amber: 'bg-amber-50 text-amber-600',
  rose: 'bg-rose-50 text-rose-600',
};

function Features() {
  return (
    <section id="features" className="py-24 bg-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-semibold mb-4">
            Everything you need
          </div>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
            One platform. Endless content.
          </h2>
          <p className="text-slate-500 max-w-xl mx-auto">
            AutoThreads handles the full content lifecycle — from AI draft to published thread — so you can focus on growing your audience.
          </p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {features.map((f) => (
            <div
              key={f.title}
              className="group p-6 rounded-2xl border border-slate-100 hover:border-brand-200 hover:shadow-card-hover transition-all bg-white"
            >
              <div className={`w-10 h-10 rounded-xl flex items-center justify-center mb-4 ${colorMap[f.color]}`}>
                {f.icon}
              </div>
              <h3 className="font-bold text-slate-900 mb-2">{f.title}</h3>
              <p className="text-sm text-slate-500 leading-relaxed">{f.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ─── How It Works ────────────────────────────────────────────────────────────

function HowItWorks() {
  const steps = [
    {
      num: '01',
      title: 'Set up your niche & affiliate links',
      desc: 'Tell AutoThreads what you promote. Add product URLs, pick a category, and configure your posting schedule.',
    },
    {
      num: '02',
      title: 'Generate & approve AI content',
      desc: 'One click generates multiple content variations. Review the quality score, approve or tweak, then add to the queue.',
    },
    {
      num: '03',
      title: 'AutoThreads publishes for you',
      desc: 'The smart scheduler posts your approved content during your best engagement windows — fully automated.',
    },
  ];

  return (
    <section id="how-it-works" className="py-24 bg-slate-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-semibold mb-4">
            Simple 3-step process
          </div>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
            Up and running in minutes
          </h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
          {/* connector line */}
          <div className="hidden md:block absolute top-10 left-1/3 right-1/3 h-px bg-gradient-to-r from-brand-200 via-brand-400 to-brand-200" />

          {steps.map((s) => (
            <div key={s.num} className="relative flex flex-col items-center text-center px-4">
              <div className="w-20 h-20 rounded-2xl bg-brand-gradient flex items-center justify-center text-white text-2xl font-extrabold shadow-glow mb-6 relative z-10">
                {s.num}
              </div>
              <h3 className="font-bold text-slate-900 mb-2 text-lg">{s.title}</h3>
              <p className="text-sm text-slate-500 leading-relaxed">{s.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ─── Video Section ────────────────────────────────────────────────────────────

function VideoSection() {
  const videoRef = useRef(null);
  const [playing, setPlaying] = useState(false);
  const [hasVideo] = useState(false); // flip to true once video file is added

  const toggle = () => {
    if (!videoRef.current) return;
    if (playing) {
      videoRef.current.pause();
    } else {
      videoRef.current.play();
    }
    setPlaying((v) => !v);
  };

  return (
    <section id="demo" className="py-24 bg-white overflow-hidden">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-semibold mb-4">
            See it in action
          </div>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
            Watch AutoThreads work its magic
          </h2>
          <p className="text-slate-500 max-w-xl mx-auto">
            From niche setup to published thread — see the full workflow in under 3 minutes.
          </p>
        </div>

        {/* Video container */}
        <div className="relative rounded-3xl overflow-hidden bg-slate-900 shadow-2xl aspect-video group cursor-pointer" onClick={toggle}>
          {/* Glow ring */}
          <div className="absolute -inset-1 bg-brand-gradient rounded-3xl blur opacity-20 pointer-events-none" />

          {hasVideo ? (
            <video
              ref={videoRef}
              className="w-full h-full object-cover"
              src="/demo.mp4"
              playsInline
              onEnded={() => setPlaying(false)}
            />
          ) : (
            /* Placeholder while video file is pending */
            <div className="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-brand-950 to-slate-900">
              {/* Decorative grid */}
              <div className="absolute inset-0 opacity-10"
                style={{
                  backgroundImage: 'linear-gradient(rgb(99 102 241 / 0.4) 1px, transparent 1px), linear-gradient(90deg, rgb(99 102 241 / 0.4) 1px, transparent 1px)',
                  backgroundSize: '40px 40px',
                }}
              />
              {/* Floating cards decoration */}
              <div className="absolute top-6 left-8 bg-white/10 backdrop-blur-sm rounded-xl p-3 text-white text-xs border border-white/10 hidden sm:block">
                <div className="font-semibold mb-1">AI Content Ready</div>
                <div className="text-white/60">Quality Score: 94/100</div>
              </div>
              <div className="absolute bottom-6 right-8 bg-white/10 backdrop-blur-sm rounded-xl p-3 text-white text-xs border border-white/10 hidden sm:block">
                <div className="font-semibold mb-1">Published to Threads</div>
                <div className="text-white/60">2 replies chained ✓</div>
              </div>

              <div className="relative z-10 flex flex-col items-center gap-4">
                <div className="w-20 h-20 rounded-full bg-white/10 border-2 border-white/30 flex items-center justify-center backdrop-blur-sm group-hover:bg-white/20 transition-all">
                  <Play className="w-8 h-8 text-white fill-white ml-1" />
                </div>
                <div className="text-center">
                  <div className="text-white font-semibold text-lg">Demo video coming soon</div>
                  <div className="text-white/50 text-sm mt-1">Video file will be added here</div>
                </div>
              </div>
            </div>
          )}

          {/* Overlay controls for actual video */}
          {hasVideo && (
            <div className={`absolute inset-0 flex items-center justify-center transition-opacity ${playing ? 'opacity-0 group-hover:opacity-100' : 'opacity-100'}`}>
              <div className="w-16 h-16 rounded-full bg-white/20 border border-white/30 flex items-center justify-center backdrop-blur-sm">
                {playing
                  ? <Pause className="w-6 h-6 text-white" />
                  : <Play className="w-6 h-6 text-white fill-white ml-0.5" />
                }
              </div>
            </div>
          )}
        </div>

        <p className="text-center text-xs text-slate-400 mt-4">
          Click to play · No sign-up required to watch
        </p>
      </div>
    </section>
  );
}

// ─── Pricing ─────────────────────────────────────────────────────────────────

const plans = [
  {
    id: 'free',
    name: 'Free',
    price: 'RM0',
    period: 'forever',
    description: 'Perfect for trying out AutoThreads.',
    highlight: false,
    badge: null,
    features: [
      '10 posts per month',
      'AI content generation',
      '3 content categories',
      'Manual scheduling',
      'Basic analytics',
      '1 Threads account',
    ],
    cta: 'Get Started Free',
    ctaStyle: 'border border-slate-200 bg-white text-slate-700 hover:border-brand-300 hover:text-brand-600',
  },
  {
    id: 'starter',
    name: 'Starter',
    price: 'RM29',
    period: 'per month',
    description: 'For creators ready to build momentum.',
    highlight: false,
    badge: null,
    features: [
      '50 posts per month',
      'All AI content categories',
      'Humanization pipeline',
      'Auto-scheduling',
      'Affiliate link integration',
      '1 Threads account',
      'Standard analytics',
    ],
    cta: 'Start Starter Plan',
    ctaStyle: 'border border-slate-200 bg-white text-slate-700 hover:border-brand-300 hover:text-brand-600',
  },
  {
    id: 'pro',
    name: 'Pro',
    price: 'RM79',
    originalPrice: 'RM120',
    period: 'per month',
    description: 'The sweet spot for serious affiliate marketers.',
    highlight: true,
    badge: '🔥 Best Value',
    discount: 'Save 34%',
    features: [
      '500 posts per month',
      'Everything in Starter',
      'Priority AI generation',
      'Hook image support',
      'Advanced analytics & insights',
      'Topic idea generator',
      'Multi-niche management',
      'Bulk scheduling',
      'Early access to new features',
    ],
    cta: 'Get Pro — Best Deal',
    ctaStyle: 'bg-brand-gradient text-white shadow-glow hover:opacity-90',
  },
  {
    id: 'enterprise',
    name: 'Enterprise',
    price: 'RM199',
    period: 'per month',
    description: 'For agencies and power users at scale.',
    highlight: false,
    badge: null,
    features: [
      '5,000 posts per month',
      'Everything in Pro',
      'Multiple Threads accounts',
      'Dedicated support',
      'Custom posting windows',
      'White-label reports',
      'API access (coming soon)',
    ],
    cta: 'Contact Sales',
    ctaStyle: 'border border-slate-200 bg-white text-slate-700 hover:border-brand-300 hover:text-brand-600',
  },
];

function Pricing() {
  return (
    <section id="pricing" className="py-24 bg-slate-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-semibold mb-4">
            Simple, transparent pricing
          </div>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
            Choose your growth plan
          </h2>
          <p className="text-slate-500 max-w-xl mx-auto">
            Start free. Upgrade when you're ready to scale. No hidden fees, cancel anytime.
          </p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch">
          {plans.map((plan) => (
            <div
              key={plan.id}
              className={`relative flex flex-col rounded-2xl p-6 transition-all ${
                plan.highlight
                  ? 'bg-slate-900 border-2 border-brand-500 shadow-glow scale-105 z-10'
                  : 'bg-white border border-slate-200 hover:border-brand-200 hover:shadow-card-hover'
              }`}
            >
              {/* Badge */}
              {plan.badge && (
                <div className="absolute -top-4 left-1/2 -translate-x-1/2 whitespace-nowrap">
                  <span className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-brand-gradient text-white text-xs font-bold shadow-lg">
                    {plan.badge}
                  </span>
                </div>
              )}

              {/* Plan name */}
              <div className="flex items-center justify-between mb-4">
                <div>
                  <div className={`flex items-center gap-1.5 font-bold text-lg ${plan.highlight ? 'text-white' : 'text-slate-900'}`}>
                    {plan.id === 'pro' && <Crown className="w-4 h-4 text-amber-400" />}
                    {plan.name}
                  </div>
                  <p className={`text-xs mt-0.5 ${plan.highlight ? 'text-slate-400' : 'text-slate-500'}`}>
                    {plan.description}
                  </p>
                </div>
              </div>

              {/* Price */}
              <div className="mb-6">
                <div className="flex items-end gap-2">
                  <span className={`text-4xl font-extrabold ${plan.highlight ? 'text-white' : 'text-slate-900'}`}>
                    {plan.price}
                  </span>
                  <span className={`text-sm mb-1.5 ${plan.highlight ? 'text-slate-400' : 'text-slate-400'}`}>
                    / {plan.period}
                  </span>
                </div>
                {plan.originalPrice && (
                  <div className="flex items-center gap-2 mt-1">
                    <span className="text-sm text-slate-400 line-through">{plan.originalPrice}</span>
                    <span className="text-xs font-bold text-emerald-400 bg-emerald-400/10 px-2 py-0.5 rounded-full">
                      {plan.discount}
                    </span>
                  </div>
                )}
              </div>

              {/* Features */}
              <ul className="flex-1 space-y-2.5 mb-8">
                {plan.features.map((f) => (
                  <li key={f} className="flex items-start gap-2 text-sm">
                    <Check className={`w-4 h-4 mt-0.5 flex-shrink-0 ${plan.highlight ? 'text-brand-400' : 'text-brand-500'}`} />
                    <span className={plan.highlight ? 'text-slate-300' : 'text-slate-600'}>{f}</span>
                  </li>
                ))}
              </ul>

              {/* CTA */}
              <Link
                to="/login"
                className={`w-full text-center py-3 rounded-xl text-sm font-semibold transition-all ${plan.ctaStyle}`}
              >
                {plan.cta}
              </Link>
            </div>
          ))}
        </div>

        {/* Bottom note */}
        <p className="text-center text-sm text-slate-400 mt-10">
          All prices in Malaysian Ringgit (MYR). Plans are billed monthly.{' '}
          <a href="#" className="text-brand-600 hover:underline">Contact us</a> for annual discounts.
        </p>
      </div>
    </section>
  );
}

// ─── Testimonials ─────────────────────────────────────────────────────────────

function Testimonials() {
  const items = [
    {
      quote: 'AutoThreads saves me 3 hours a day. My affiliate content finally sounds like me, not a robot.',
      name: 'Amir H.',
      role: 'Affiliate Marketer, KL',
    },
    {
      quote: 'The humanization feature is insane — I pass 100% of AI detectors and engagement went up 40%.',
      name: 'Syaza R.',
      role: 'Content Creator, JB',
    },
    {
      quote: 'Best investment for my Shopee affiliate strategy. The scheduler posts while I sleep.',
      name: 'Farid K.',
      role: 'E-commerce Seller, Penang',
    },
  ];

  return (
    <section className="py-24 bg-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-14">
          <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
            Creators love AutoThreads
          </h2>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {items.map((t) => (
            <div key={t.name} className="p-6 rounded-2xl border border-slate-100 bg-white hover:shadow-card-hover transition-all">
              <div className="flex mb-3">
                {[...Array(5)].map((_, i) => (
                  <Star key={i} className="w-4 h-4 text-amber-400 fill-amber-400" />
                ))}
              </div>
              <p className="text-slate-600 text-sm leading-relaxed mb-4">"{t.quote}"</p>
              <div>
                <div className="font-semibold text-slate-900 text-sm">{t.name}</div>
                <div className="text-xs text-slate-400">{t.role}</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ─── CTA Banner ───────────────────────────────────────────────────────────────

function CTABanner() {
  return (
    <section className="py-24 bg-slate-900 relative overflow-hidden">
      <div className="absolute inset-0 bg-mesh-dark pointer-events-none" />
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-96 h-96 rounded-full bg-brand-500/10 blur-3xl pointer-events-none" />

      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <div className="w-14 h-14 rounded-2xl bg-brand-gradient flex items-center justify-center mx-auto mb-6 shadow-glow">
          <Zap className="w-7 h-7 text-white" strokeWidth={2.5} />
        </div>
        <h2 className="text-3xl sm:text-4xl font-extrabold text-white mb-4">
          Ready to automate your Threads?
        </h2>
        <p className="text-slate-400 mb-8 max-w-lg mx-auto">
          Join creators who are already publishing AI-crafted, human-sounding affiliate content — on autopilot.
        </p>
        <div className="flex flex-col sm:flex-row gap-4 justify-center">
          <Link
            to="/login"
            className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-brand-gradient text-white font-semibold shadow-glow hover:opacity-90 transition-all hover:scale-105"
          >
            Start Free Today <ArrowRight className="w-4 h-4" />
          </Link>
          <a
            href="#pricing"
            className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white/10 border border-white/20 text-white font-semibold hover:bg-white/20 transition-all"
          >
            View Pricing
          </a>
        </div>
      </div>
    </section>
  );
}

// ─── Footer ───────────────────────────────────────────────────────────────────

function Footer() {
  return (
    <footer className="bg-slate-950 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col md:flex-row items-center justify-between gap-6">
          {/* Brand */}
          <div className="flex items-center gap-2.5">
            <div className="w-7 h-7 rounded-lg bg-brand-gradient flex items-center justify-center">
              <Zap className="w-3.5 h-3.5 text-white" strokeWidth={2.5} />
            </div>
            <span className="text-white font-bold">AutoThreads</span>
            <span className="text-slate-600 text-sm">· AI Content Automation</span>
          </div>

          {/* Links */}
          <div className="flex items-center gap-6 text-sm text-slate-500">
            <a href="#features" className="hover:text-slate-300 transition-colors">Features</a>
            <a href="#pricing" className="hover:text-slate-300 transition-colors">Pricing</a>
            <Link to="/login" className="hover:text-slate-300 transition-colors">Login</Link>
          </div>

          <p className="text-xs text-slate-600">
            © {new Date().getFullYear()} AutoThreads. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Landing() {
  return (
    <div className="min-h-screen font-sans antialiased">
      <Navbar />
      <main>
        <Hero />
        <Stats />
        <Features />
        <HowItWorks />
        <VideoSection />
        <Testimonials />
        <Pricing />
        <CTABanner />
      </main>
      <Footer />
    </div>
  );
}
