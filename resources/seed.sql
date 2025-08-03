-- Seed data for ZORNELL database

-- Clear existing data
DELETE FROM sessions;
DELETE FROM notes;
DELETE FROM users;

-- Insert test user (password: al3rezal3rez)
-- Password hash generated with PHP password_hash('al3rezal3rez', PASSWORD_DEFAULT)
INSERT INTO users (email, password_hash, created_at, last_login) VALUES 
('al3rez@gmail.com', '$2y$12$NOL4.jBwd6J9eql0UgoUAucO5LlZfDdp5dN0vVKZoTzhYuD6tEX3i', datetime('now'), datetime('now'));

-- Get the user ID (should be 1 after clearing)
-- Insert diverse notes for the user
INSERT INTO notes (id, user_id, title, content, type, urgent, date, created_at, updated_at) VALUES 
-- Work notes
('note_001', 1, 'Q1 2025 Product Roadmap', 'Key Deliverables:
• Launch mobile app v2.0
• Implement real-time collaboration
• Add voice-to-text feature
• Integrate with Slack and Teams
• Performance optimization sprint

Timeline:
- Jan: Mobile app beta
- Feb: Collaboration features
- Mar: Integration and launch', 'work', 1, date('now'), datetime('now'), datetime('now')),

('note_002', 1, 'Team Meeting Notes - Sprint Review', 'Attendees: Sarah, Mike, Jessica, Tom

Completed:
✓ User authentication system
✓ Database optimization
✓ API rate limiting
✓ Frontend refactoring

Blockers:
- AWS deployment issues
- Need design review for mobile UI
- Waiting on security audit results

Action Items:
• Mike: Fix deployment scripts by EOD
• Sarah: Schedule design review
• Tom: Follow up with security team', 'work', 0, date('now'), datetime('now'), datetime('now')),

('note_003', 1, 'Client Project - ABC Corp', 'Project Requirements:
1. Custom dashboard with real-time analytics
2. User role management (Admin, Manager, Viewer)
3. Export functionality (PDF, Excel, CSV)
4. API for third-party integrations
5. Mobile responsive design

Budget: $150,000
Timeline: 3 months
Next milestone: March 15th

Contact: john.doe@abccorp.com
Phone: (555) 123-4567', 'work', 1, date('now'), datetime('now'), datetime('now')),

('note_004', 1, 'Code Review Checklist', 'Before submitting PR:
□ All tests passing
□ Code follows style guide
□ Documentation updated
□ No console.log statements
□ Error handling implemented
□ Performance considered
□ Security best practices
□ Accessibility checked

Review points:
• Is the code DRY?
• Are variable names descriptive?
• Is the logic clear?
• Are there edge cases?
• Is it maintainable?', 'work', 0, date('now'), datetime('now'), datetime('now')),

('note_005', 1, 'API Endpoints Documentation', 'Authentication:
POST /api/auth/login
POST /api/auth/register
POST /api/auth/logout
GET /api/auth/verify

Users:
GET /api/users/:id
PUT /api/users/:id
DELETE /api/users/:id

Notes:
GET /api/notes
POST /api/notes
PUT /api/notes/:id
DELETE /api/notes/:id

Headers required:
Authorization: Bearer <token>
Content-Type: application/json', 'work', 0, date('now'), datetime('now'), datetime('now')),

-- Personal notes
('note_006', 1, 'Weekend Trip Planning', 'Destination: Lake Tahoe
Dates: March 21-24

Packing List:
• Hiking boots
• Warm jacket
• Camera + extra batteries
• Sunscreen
• First aid kit
• Snacks and water bottles

Activities:
- Emerald Bay State Park hike
- Kayaking on the lake
- Visit Vikingsholm Castle
- Sunset photography at Sand Harbor

Budget: $800', 'personal', 0, date('now'), datetime('now'), datetime('now')),

('note_007', 1, 'Workout Routine', 'Monday/Thursday - Upper Body:
• Bench press: 4x8
• Pull-ups: 3x10
• Shoulder press: 3x12
• Bicep curls: 3x15
• Tricep dips: 3x12

Tuesday/Friday - Lower Body:
• Squats: 4x10
• Deadlifts: 3x8
• Leg press: 3x12
• Calf raises: 4x15
• Lunges: 3x10 each leg

Wednesday - Cardio:
• 30 min run or bike
• 15 min HIIT

Remember: Stretch before and after!', 'personal', 0, date('now'), datetime('now'), datetime('now')),

('note_008', 1, 'Book Reading List 2025', 'Currently Reading:
📖 "Atomic Habits" - James Clear

To Read:
• "The Pragmatic Programmer" - David Thomas
• "Deep Work" - Cal Newport
• "The Phoenix Project" - Gene Kim
• "Clean Code" - Robert Martin
• "Sapiens" - Yuval Noah Harari
• "The Lean Startup" - Eric Ries

Completed:
✓ "The Martian" - Andy Weir (★★★★★)
✓ "1984" - George Orwell (★★★★☆)
✓ "Dune" - Frank Herbert (★★★★★)', 'personal', 0, date('now'), datetime('now'), datetime('now')),

('note_009', 1, 'Recipe: Homemade Pizza Dough', 'Ingredients:
• 3 cups bread flour
• 1 tbsp sugar
• 1 packet instant yeast
• 1 tbsp salt
• 2 tbsp olive oil
• 1 cup warm water

Instructions:
1. Mix dry ingredients in large bowl
2. Add oil and water, mix until shaggy
3. Knead for 8-10 minutes until smooth
4. Place in oiled bowl, cover
5. Let rise 1-2 hours until doubled
6. Punch down, divide into 2 portions
7. Roll out and add toppings

Bake at 475°F for 12-15 minutes', 'personal', 0, date('now'), datetime('now'), datetime('now')),

('note_010', 1, 'Home Improvement Projects', 'Priority Order:
1. Fix leaking bathroom faucet
2. Paint bedroom walls (blue/gray)
3. Install smart thermostat
4. Replace kitchen cabinet handles
5. Build garden raised beds
6. Organize garage storage

Shopping List:
- Faucet washers
- Paint (2 gallons)
- Brushes and rollers
- Drop cloths
- Cabinet handles (12)
- Wood for raised beds
- Soil and compost', 'personal', 0, date('now'), datetime('now'), datetime('now')),

-- Urgent notes
('note_011', 1, 'URGENT: Server Migration', 'Migration scheduled: Tonight 11 PM PST

Pre-migration checklist:
☐ Full database backup completed
☐ DNS TTL reduced to 5 minutes
☐ Load balancer configured
☐ SSL certificates ready
☐ Monitoring alerts set up

Team on call:
- DevOps: Mike (primary)
- Backend: Sarah (backup)
- Frontend: Tom (if needed)

Rollback plan documented in wiki', 'work', 1, date('now'), datetime('now'), datetime('now')),

('note_012', 1, 'Doctor Appointment Tomorrow', 'Dr. Smith - Annual Checkup
Time: 9:30 AM
Location: Medical Center, 4th Floor

Remember to bring:
• Insurance card
• List of current medications
• Previous lab results

Questions to ask:
- Cholesterol levels?
- Need for vitamin D supplement?
- Exercise recommendations?

Fasting required - no food after midnight!', 'personal', 1, date('now'), datetime('now'), datetime('now')),

('note_013', 1, 'Tax Documents Deadline', 'Due: April 15th

Documents needed:
☐ W-2 from employer
☐ 1099-MISC from freelance work
☐ Investment statements
☐ Mortgage interest (1098)
☐ Charitable donations receipts
☐ Medical expenses over $7,500

CPA appointment: March 20th @ 2 PM
Email: cpa@taxservices.com

Estimated refund: $2,400', 'personal', 1, date('now'), datetime('now'), datetime('now')),

('note_014', 1, 'Production Bug - High Priority', 'Bug: Users unable to export PDF reports

Error: "Failed to generate PDF: Timeout"
Affected users: ~500
Started: 2 hours ago

Investigation:
- PDF service memory leak suspected
- Queue backlog at 10,000 jobs
- Server CPU at 95%

Temporary fix:
1. Restart PDF service
2. Increase timeout to 60s
3. Add job retry logic

Permanent fix needed by Monday!', 'work', 1, date('now'), datetime('now'), datetime('now')),

-- Mixed type notes
('note_015', 1, 'Interview Prep - Senior Dev Position', 'Company: TechCorp Inc
Position: Senior Full Stack Developer
Date: Next Tuesday, 2 PM

Technical topics to review:
• System design patterns
• Database optimization
• Microservices architecture
• React hooks deep dive
• Node.js performance tuning
• AWS services (EC2, S3, Lambda)

Behavioral questions prep:
- Challenging project example
- Team conflict resolution
- Leadership experience
- Why leaving current job?

Research company products & culture', 'work', 0, date('now'), datetime('now'), datetime('now')),

('note_016', 1, 'Investment Portfolio Review', 'Current Holdings:
• VTSAX (Total Market): $25,000 (40%)
• VTIAX (International): $15,000 (24%)
• VBTLX (Bonds): $10,000 (16%)
• Individual Stocks: $12,500 (20%)
  - AAPL: $3,000
  - GOOGL: $2,500
  - AMZN: $2,000
  - TSLA: $2,000
  - MSFT: $3,000

Monthly contribution: $2,000
Target allocation: 70/20/10
Rebalance quarterly

Next review: March 31', 'personal', 0, date('now'), datetime('now'), datetime('now')),

('note_017', 1, 'Learning Path - AI/ML', 'Current Progress:
✓ Python basics
✓ NumPy and Pandas
✓ Basic statistics
→ Linear algebra (in progress)

Next Steps:
1. Complete Andrew Ng ML course
2. Implement neural network from scratch
3. Learn TensorFlow/PyTorch
4. Work on Kaggle competitions
5. Build portfolio project

Resources:
• Fast.ai courses
• Papers with Code
• Google Colab for practice
• "Pattern Recognition and ML" book', 'personal', 0, date('now'), datetime('now'), datetime('now')),

('note_018', 1, 'Team Standup Template', 'Daily Standup Format:

Yesterday:
• What did I complete?
• Any blockers resolved?

Today:
• Top 3 priorities
• Meetings scheduled
• Code reviews needed

Blockers:
• Technical issues?
• Waiting on dependencies?
• Need help from team?

Remember:
- Keep it under 2 minutes
- Be specific about tasks
- Raise blockers early', 'work', 0, date('now'), datetime('now'), datetime('now')),

('note_019', 1, 'Emergency Contacts', 'Family:
• Mom: (555) 111-2222
• Dad: (555) 111-3333
• Sister: (555) 111-4444

Medical:
• Primary Care: Dr. Smith (555) 222-3333
• Dentist: Dr. Jones (555) 333-4444
• Hospital: City General (555) 444-5555

Services:
• Plumber: Joe''s Plumbing (555) 555-6666
• Electrician: Spark Electric (555) 666-7777
• Auto Mechanic: Mike''s Garage (555) 777-8888

Insurance:
• Health: Policy #12345678
• Auto: Policy #87654321
• Home: Policy #11223344', 'personal', 1, date('now'), datetime('now'), datetime('now')),

('note_020', 1, 'Project Architecture Notes', 'Frontend:
- React 18 with TypeScript
- Redux Toolkit for state
- Material-UI components
- React Query for API calls

Backend:
- Node.js with Express
- PostgreSQL database
- Redis for caching
- JWT authentication

Infrastructure:
- Docker containers
- Kubernetes orchestration
- AWS hosting (us-west-2)
- CloudFlare CDN
- GitHub Actions CI/CD

Monitoring:
- Datadog for metrics
- Sentry for errors
- LogRocket for sessions', 'work', 0, date('now'), datetime('now'), datetime('now'));