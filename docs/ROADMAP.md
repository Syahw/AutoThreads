# AutoThreads Development Roadmap

## Phase 1: Foundation (Week 1-2)

### Backend Setup
- [ ] Install PHP 8.2, Composer, MySQL 8.0, Redis
- [ ] Run `composer install` in backend/
- [ ] Configure `.env` from `.env.example`
- [ ] Run database migrations (schema.sql + schema_part2.sql)
- [ ] Verify API health check at `/health`

### Frontend Setup
- [ ] Run `npm install` in frontend/
- [ ] Verify dev server at `localhost:3000`
- [ ] Test login/register flow

### Core Features
- [ ] User registration and login (JWT)
- [ ] Niche CRUD operations
- [ ] Affiliate link management
- [ ] Basic dashboard stats

---

## Phase 2: AI Content Engine (Week 3-4)

### Content Generation
- [ ] Connect OpenAI API key
- [ ] Test single post generation
- [ ] Test variation generation (3 variations)
- [ ] Verify humanizer removes AI patterns
- [ ] Verify quality scoring works
- [ ] Test all 9 content categories
- [ ] Test tone rotation

### Topic Generator
- [ ] Build topic generation endpoint
- [ ] Implement duplicate detection
- [ ] Auto-categorize generated topics
- [ ] Topic priority scoring

### Content Moderation
- [ ] Blacklist word filtering
- [ ] Manual approval workflow
- [ ] Edit before publish
- [ ] Regeneration with different params

---

## Phase 3: Threads Integration (Week 5-6)

### OAuth Setup
- [ ] Register Meta Developer App
- [ ] Configure Threads API credentials
- [ ] Implement OAuth flow (connect account)
- [ ] Token refresh mechanism
- [ ] Multi-account support

### Publishing
- [ ] Two-step publish (container → publish)
- [ ] Error handling for rate limits
- [ ] Retry logic for failed posts
- [ ] Post verification after publish

---

## Phase 4: Scheduler System (Week 7-8)

### Scheduling
- [ ] Manual scheduling (pick date/time)
- [ ] Auto-scheduling (daily cron)
- [ ] Randomized posting times
- [ ] Daily limit enforcement
- [ ] Calendar view in dashboard

### Cron Jobs
- [ ] Set up crontab on server
- [ ] `publish_posts.php` - every minute
- [ ] `auto_schedule.php` - daily at midnight
- [ ] `collect_analytics.php` - every 6 hours
- [ ] Log rotation and monitoring

---

## Phase 5: Analytics & Optimization (Week 9-10)

### Analytics Collection
- [ ] Fetch post insights from Threads API
- [ ] Store engagement metrics
- [ ] Calculate CTR and engagement rates
- [ ] Best posting times analysis
- [ ] Best performing hooks analysis

### AI Optimization
- [ ] Score hooks by actual engagement
- [ ] Rank content categories by performance
- [ ] Feed top-performing patterns back to prompts
- [ ] A/B test different tones
- [ ] Engagement prediction model (v1)

---

## Phase 6: Production Hardening (Week 11-12)

### Security
- [ ] Rate limiting (API + AI calls)
- [ ] Input validation on all endpoints
- [ ] SQL injection prevention (Eloquent handles this)
- [ ] XSS prevention in frontend
- [ ] CSRF protection
- [ ] Secrets management (no .env in git)

### Performance
- [ ] Redis caching for dashboard stats
- [ ] Database query optimization
- [ ] API response pagination
- [ ] Frontend lazy loading
- [ ] Image/asset CDN

### Monitoring
- [ ] Error logging (Monolog)
- [ ] API response time tracking
- [ ] Cron job health checks
- [ ] Disk space monitoring
- [ ] Database backup automation

---

## Phase 7: SaaS Features (Week 13-16)

### Multi-tenancy
- [ ] Plan-based feature gating
- [ ] Usage limits per plan
- [ ] Billing integration (Stripe)
- [ ] User onboarding flow
- [ ] Team/organization support

### Plans
| Feature | Free | Starter | Pro | Enterprise |
|---------|------|---------|-----|------------|
| Posts/day | 1 | 3 | 5 | Unlimited |
| Niches | 1 | 3 | 10 | Unlimited |
| Accounts | 1 | 2 | 5 | Unlimited |
| Analytics | Basic | Full | Full | Full + API |
| AI Model | GPT-3.5 | GPT-4 | GPT-4 | Custom |

### Additional SaaS Features
- [ ] White-label option
- [ ] API access for enterprise
- [ ] Webhook notifications
- [ ] Custom prompt templates
- [ ] Priority support queue

---

## Deployment Strategy

### VPS Requirements
- Ubuntu 22.04 LTS
- 2+ CPU cores, 4GB+ RAM
- PHP 8.2, MySQL 8.0, Redis 7, Nginx
- SSL via Let's Encrypt
- Supervisor for queue workers

### Crontab Configuration
```
* * * * * php /var/www/autothreads/backend/cron/publish_posts.php
0 0 * * * php /var/www/autothreads/backend/cron/auto_schedule.php
0 */6 * * * php /var/www/autothreads/backend/cron/collect_analytics.php
```

### Nginx Config (API)
```nginx
server {
    listen 80;
    server_name api.autothreads.com;
    root /var/www/autothreads/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## Architecture Decisions

| Decision | Choice | Why |
|----------|--------|-----|
| Framework | Slim 4 | Lightweight, no bloat, fast for APIs |
| ORM | Eloquent (standalone) | Familiar, powerful, works outside Laravel |
| Auth | JWT | Stateless, scales horizontally |
| Queue | Redis | Fast, doubles as cache |
| AI | OpenAI GPT-4 | Best quality for human-like text |
| Frontend | React + Vite | Fast dev, component reuse for SaaS |
| State | Zustand | Simple, no boilerplate |
| Styling | Tailwind | Rapid UI development |

---

## Future Monetization Ideas

1. **SaaS Subscriptions** - Monthly plans with tiered features
2. **Marketplace** - Sell prompt templates and niche packs
3. **Agency Mode** - Manage multiple client accounts
4. **API Access** - Let developers build on top
5. **White Label** - Resell as their own brand
6. **Affiliate Network** - Connect brands with content creators
7. **AI Training** - Custom fine-tuned models per niche
8. **Analytics Reports** - Premium PDF reports for clients
