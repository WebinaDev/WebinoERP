/** Verbatim About-page copy (about-brief.md). Seeded into the «درباره ما» builder document; also the fallback before it exists. */
import type { AboutPageData } from './about-content';

export const ABOUT_DEFAULTS: Record<'fa' | 'en', AboutPageData> = {
  "fa": {
    "sections": [
      {
        "type": "hero",
        "enabled": true,
        "props": {
          "kicker": "وبینا | آژانس توسعه کسب‌وکار",
          "title": "ما نتیجه‌ می‌سازیم، نه فقط خروجی",
          "text": "از ایده تا اجرا، از طراحی تا رشد؛ یک تیم یکپارچه برای ساختن کسب‌وکارهای واقعی",
          "buttons": [
            {
              "label": "رزرو جلسه آشنایی",
              "href": "consultation",
              "style": "primary"
            }
          ],
          "tags": [
            {
              "text": "تیم یکپارچه"
            },
            {
              "text": "از ایده تا نتیجه"
            }
          ]
        }
      },
      {
        "type": "intro",
        "enabled": true,
        "props": {
          "title": "معرفی رسمی",
          "lead": "وبینا (Webina) آژانس تخصصی توسعه کسب‌وکار است؛ با تمرکز بر طراحی، اجرا و رشد کسب‌وکار از صفر تا نتیجه.",
          "logo_caption": "شرکت توسعه کسب‌وکار دز آوان",
          "facts": [
            {
              "label": "نام آژانس",
              "value": "وبینا (Webina)",
              "icon": "Sparkles"
            },
            {
              "label": "نام شرکت",
              "value": "شرکت توسعه کسب‌وکار دز آوان",
              "icon": "Building2"
            },
            {
              "label": "نقش",
              "value": "آژانس تخصصی توسعه کسب‌وکار",
              "icon": "Briefcase"
            },
            {
              "label": "دامنه",
              "value": "webina.dev / webinaagency.ir",
              "icon": "Globe"
            },
            {
              "label": "تمرکز",
              "value": "طراحی، اجرا و رشد کسب‌وکار از صفر تا نتیجه",
              "icon": "Target"
            }
          ]
        }
      },
      {
        "type": "story",
        "enabled": true,
        "props": {
          "title": "داستان ما",
          "paragraphs": [
            {
              "text": "ما بارها دیدیم کسب‌وکارهایی که سایت زیبا داشتند اما فروش نداشتند.\nبرند قشنگ داشتند اما سیستم نداشتند.\nایده عالی داشتند اما اجرا نمی‌شد."
            },
            {
              "text": "مشکل کمبود ایده نبود. مشکل پراکندگی بود."
            },
            {
              "text": "یک نفر استراتژی می‌نوشت، یکی طراحی می‌کرد، یکی توسعه می‌داد، یکی تبلیغات می‌کرد…\nو در نهایت هیچ‌کس مسئولیت نتیجه را به عهده نمی‌گرفت."
            },
            {
              "text": "وبینا از همین نقطه متولد شد.\nما آمدیم تا فاصله بین ایده و نتیجه را از بین ببریم.\nتا استراتژی، طراحی، توسعه و رشد را در یک مسیر واحد و تحت مسئولیت یک تیم جمع کنیم."
            },
            {
              "text": "امروز وبینا بازوی اجرایی شرکت توسعه کسب‌وکار دز آوان است؛\nجایی که کسب‌وکارها دیگر مجبور نیستند بین چند پیمانکار سرگردان باشند."
            }
          ],
          "quote": "ما پیمانکار موقت نیستیم؛ شریک مسیر رشد هستیم",
          "path": [
            {
              "text": "ایده"
            },
            {
              "text": "طراحی"
            },
            {
              "text": "اجرا"
            },
            {
              "text": "رشد"
            }
          ]
        }
      },
      {
        "type": "mission",
        "enabled": true,
        "props": {
          "title": "مأموریت و چشم‌انداز",
          "items": [
            {
              "title": "مأموریت",
              "text": "مأموریت ما تبدیل ایده‌ها به سیستم‌های واقعی درآمدزا و قابل رشد است.\nما کسب‌وکارها را از حالت پراکنده خارج می‌کنیم و به یک ساختار منسجم، هوشمند و مقیاس‌پذیر می‌رسانیم.",
              "icon": "Rocket"
            },
            {
              "title": "چشم‌انداز",
              "text": "می‌خواهیم وبینا انتخاب اول کسانی باشد که به‌جای کارهای سطحی، به دنبال ساختن اصولی و ماندگار هستند.",
              "icon": "Telescope"
            }
          ]
        }
      },
      {
        "type": "values",
        "enabled": true,
        "props": {
          "title": "ارزش‌های ما",
          "items": [
            {
              "title": "نتیجه‌گرایی",
              "text": "هر تصمیم را با اثرش روی فروش و رشد واقعی می‌سنجیم.",
              "icon": "Target"
            },
            {
              "title": "یکپارچگی",
              "text": "استراتژی، طراحی، توسعه و رشد را در یک مسیر واحد پیش می‌بریم.",
              "icon": "Layers"
            },
            {
              "title": "شفافیت",
              "text": "فرآیند، هزینه و پیشرفت کار همیشه برای شما روشن است.",
              "icon": "Eye"
            },
            {
              "title": "مالکیت نتیجه",
              "text": "مسئولیت خروجی را تا رسیدن به نتیجه به عهده می‌گیریم.",
              "icon": "ShieldCheck"
            },
            {
              "title": "بهبود مستمر",
              "text": "بعد از هر مرحله می‌سنجیم، یاد می‌گیریم و بهتر می‌کنیم.",
              "icon": "RefreshCw"
            }
          ]
        }
      },
      {
        "type": "approach",
        "enabled": true,
        "props": {
          "title": "رویکرد ما (چگونه کار می‌کنیم)",
          "lead": "ما کسب‌وکار را مثل یک سیستم زنده می‌بینیم.\nقبل از هر طراحی یا کدنویسی، اول می‌پرسیم:",
          "steps": [
            {
              "title": "برای چه کسی؟",
              "text": ""
            },
            {
              "title": "چه مشکلی را حل می‌کند؟",
              "text": ""
            },
            {
              "title": "مسیر درآمد چیست؟",
              "text": ""
            },
            {
              "title": "چطور باید رشد کند؟",
              "text": ""
            }
          ],
          "outro": "بعد از رسیدن به پاسخ‌های روشن، طراحی، توسعه و اجرا را آغاز می‌کنیم."
        }
      },
      {
        "type": "team",
        "enabled": true,
        "props": {
          "title": "تیم وبینا",
          "text": "ما تیمی از متخصصان استراتژی، طراحی، توسعه، اتوماسیون و رشد هستیم که زیر چتر وبینا و شرکت توسعه کسب‌وکار دز آوان کار می‌کنیم.",
          "roles": [
            {
              "title": "استراتژی و مدل کسب‌وکار",
              "icon": "Compass"
            },
            {
              "title": "طراحی هویت و تجربه کاربری",
              "icon": "PenTool"
            },
            {
              "title": "طراحی و توسعه وب و نرم‌افزار",
              "icon": "Code"
            },
            {
              "title": "اتوماسیون و سیستم‌سازی",
              "icon": "Workflow"
            },
            {
              "title": "رشد و بهینه‌سازی فروش",
              "icon": "TrendingUp"
            },
            {
              "title": "مدیریت پروژه و همراهی مشتری",
              "icon": "Handshake"
            }
          ],
          "members": [],
          "culture_title": "فرهنگ تیمی",
          "culture": [
            {
              "text": "دقت"
            },
            {
              "text": "مسئولیت‌پذیری"
            },
            {
              "text": "نتیجه‌محوری"
            },
            {
              "text": "یادگیری"
            }
          ]
        }
      },
      {
        "type": "different",
        "enabled": true,
        "props": {
          "title": "آنچه ما را متفاوت می‌کند",
          "items": [
            {
              "text": "یک تیم واحد به جای چند پیمانکار"
            },
            {
              "text": "اتصال استراتژی به اجرا"
            },
            {
              "text": "تمرکز روی شاخص‌های واقعی کسب‌وکار"
            },
            {
              "text": "ماندن بعد از تحویل پروژه"
            },
            {
              "text": "نگاه بلندمدت به رشد"
            }
          ]
        }
      },
      {
        "type": "cta",
        "enabled": true,
        "props": {
          "title": "آماده‌ای کسب‌وکارت را اصولی بسازی؟",
          "text": "اگر از کارهای نصفه و نیمه خسته شده‌ای و به یک تیم یکپارچه برای مسیر رشد نیاز داری، ما اینجاییم.",
          "buttons": [
            {
              "label": "رزرو جلسه آشنایی",
              "href": "consultation",
              "style": "primary"
            },
            {
              "label": "مشاهده مسیر همکاری",
              "href": "/#process",
              "style": "ghost"
            },
            {
              "label": "تماس با ما",
              "href": "contact",
              "style": "ghost"
            }
          ]
        }
      }
    ]
  },
  "en": {
    "sections": [
      {
        "type": "hero",
        "enabled": true,
        "props": {
          "kicker": "Webina | Business development agency",
          "title": "We build results, not just deliverables",
          "text": "From idea to execution, from design to growth: one integrated team for building real businesses.",
          "buttons": [
            {
              "label": "Book an intro call",
              "href": "consultation",
              "style": "primary"
            }
          ],
          "tags": [
            {
              "text": "One integrated team"
            },
            {
              "text": "From idea to result"
            }
          ]
        }
      },
      {
        "type": "intro",
        "enabled": true,
        "props": {
          "title": "Who we are",
          "lead": "Webina is a specialist business development agency focused on designing, executing and growing businesses from zero to results.",
          "logo_caption": "Dez Avan Business Development Co.",
          "facts": [
            {
              "label": "Agency",
              "value": "Webina",
              "icon": "Sparkles"
            },
            {
              "label": "Company",
              "value": "Dez Avan Business Development Co.",
              "icon": "Building2"
            },
            {
              "label": "Role",
              "value": "Business development agency",
              "icon": "Briefcase"
            },
            {
              "label": "Domains",
              "value": "webina.dev / webinaagency.ir",
              "icon": "Globe"
            },
            {
              "label": "Focus",
              "value": "Designing, executing and growing businesses",
              "icon": "Target"
            }
          ]
        }
      },
      {
        "type": "story",
        "enabled": true,
        "props": {
          "title": "Our story",
          "paragraphs": [
            {
              "text": "We kept seeing businesses with beautiful sites but no sales, nice brands but no systems, great ideas that never shipped."
            },
            {
              "text": "The problem was never a lack of ideas. It was fragmentation."
            },
            {
              "text": "Webina was born to close the gap between idea and result, under one accountable team."
            }
          ],
          "quote": "We are not a temporary contractor; we are a partner in your growth.",
          "path": [
            {
              "text": "Idea"
            },
            {
              "text": "Design"
            },
            {
              "text": "Execution"
            },
            {
              "text": "Growth"
            }
          ]
        }
      },
      {
        "type": "mission",
        "enabled": true,
        "props": {
          "title": "Mission & vision",
          "items": [
            {
              "title": "Mission",
              "text": "Turning ideas into real, revenue-generating systems that can grow.",
              "icon": "Rocket"
            },
            {
              "title": "Vision",
              "text": "To be the first choice for those who want to build properly and to last.",
              "icon": "Telescope"
            }
          ]
        }
      },
      {
        "type": "values",
        "enabled": true,
        "props": {
          "title": "Our values",
          "items": [
            {
              "title": "Results",
              "text": "We judge every decision by its effect on real growth.",
              "icon": "Target"
            },
            {
              "title": "Integration",
              "text": "Strategy, design, build and growth on one path.",
              "icon": "Layers"
            },
            {
              "title": "Transparency",
              "text": "Process, cost and progress are always clear.",
              "icon": "Eye"
            },
            {
              "title": "Ownership",
              "text": "We own the outcome until it is reached.",
              "icon": "ShieldCheck"
            },
            {
              "title": "Improvement",
              "text": "We measure, learn and improve after every stage.",
              "icon": "RefreshCw"
            }
          ]
        }
      },
      {
        "type": "approach",
        "enabled": true,
        "props": {
          "title": "Our approach",
          "lead": "We see a business as a living system. Before any design or code, we ask:",
          "steps": [
            {
              "title": "For whom?",
              "text": ""
            },
            {
              "title": "What problem does it solve?",
              "text": ""
            },
            {
              "title": "Where does revenue come from?",
              "text": ""
            },
            {
              "title": "How should it grow?",
              "text": ""
            }
          ],
          "outro": "With clear answers, we start design, development and execution."
        }
      },
      {
        "type": "team",
        "enabled": true,
        "props": {
          "title": "The Webina team",
          "text": "Specialists in strategy, design, development, automation and growth.",
          "roles": [
            {
              "title": "Strategy & business model",
              "icon": "Compass"
            },
            {
              "title": "Brand & UX design",
              "icon": "PenTool"
            },
            {
              "title": "Web & software development",
              "icon": "Code"
            },
            {
              "title": "Automation & systems",
              "icon": "Workflow"
            },
            {
              "title": "Growth & sales optimisation",
              "icon": "TrendingUp"
            },
            {
              "title": "Project management & client care",
              "icon": "Handshake"
            }
          ],
          "members": [],
          "culture_title": "Team culture",
          "culture": [
            {
              "text": "Precision"
            },
            {
              "text": "Accountability"
            },
            {
              "text": "Results"
            },
            {
              "text": "Learning"
            }
          ]
        }
      },
      {
        "type": "different",
        "enabled": true,
        "props": {
          "title": "What makes us different",
          "items": [
            {
              "text": "One team instead of many contractors"
            },
            {
              "text": "Strategy connected to execution"
            },
            {
              "text": "Focus on real business metrics"
            },
            {
              "text": "Staying after delivery"
            },
            {
              "text": "A long-term view of growth"
            }
          ]
        }
      },
      {
        "type": "cta",
        "enabled": true,
        "props": {
          "title": "Ready to build your business properly?",
          "text": "If you are tired of half-done work and need one integrated team for growth, we are here.",
          "buttons": [
            {
              "label": "Book an intro call",
              "href": "consultation",
              "style": "primary"
            },
            {
              "label": "See how we work",
              "href": "/#process",
              "style": "ghost"
            },
            {
              "label": "Contact us",
              "href": "contact",
              "style": "ghost"
            }
          ]
        }
      }
    ]
  }
} as Record<'fa' | 'en', AboutPageData>;
