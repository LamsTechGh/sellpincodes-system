# Sellpincodes System - Complete PHP Backend & Frontend

A complete clone of sellpincodes.com with professional design and full PHP/MySQL backend integration.

## Features

### Frontend
- ✅ Professional modern design with blue color scheme
- ✅ Responsive layout for all devices
- ✅ Interactive modals for all services
- ✅ Real-time form validation
- ✅ Backend API integration
- ✅ Clean, accessible UI without external icons

### Backend
- ✅ Complete PHP backend with MySQL database
- ✅ RESTful API endpoints
- ✅ Transaction management system
- ✅ Checker/voucher generation
- ✅ Mobile money payment simulation
- ✅ SMS notification system
- ✅ Admin dashboard
- ✅ Comprehensive error handling

### Services
- **WAEC Results Checker** - Multiple exam types with tiered pricing
- **SHS Placement Checker** - School placement verification
- **UCC Admission Forms** - University application forms
- **Retrieval System** - Recover old checkers using transaction/print ID

## Project Structure

```
/
├── index.html              # Main frontend page
├── admin.html              # Admin dashboard
├── assets/
│   ├── css/
│   │   └── styles.css      # Professional styling
│   ├── js/
│   │   └── main.js         # Frontend logic with backend integration
│   └── img/                # Images directory
├── backend/
│   ├── api/
│   │   ├── index.php       # API router
│   │   ├── purchase.php    # Purchase endpoint
│   │   ├── retrieve.php    # Retrieval endpoint
│   │   ├── services.php    # Services data endpoint
│   │   └── admin.php       # Admin dashboard API
│   ├── config/
│   │   └── database.php    # Database configuration
│   ├── models/
│   │   ├── BaseModel.php   # Base model class
│   │   ├── Transaction.php # Transaction model
│   │   └── Checker.php     # Checker model
│   ├── utils/
│   │   ├── PaymentProcessor.php # Payment handling
│   │   └── SMSHandler.php  # SMS notifications
│   └── setup/
│       └── database_setup.sql # Database schema
└── README.md
```

## Setup Instructions

### 1. Database Setup

1. Create a MySQL database named `sellpincodes_db`
2. Import the database schema:
   ```bash
   mysql -u root -p sellpincodes_db < backend/setup/database_setup.sql
   ```

### 2. Configuration

1. Update database credentials in `backend/config/database.php`:
   ```php
   private $host = 'localhost';
   private $db_name = 'sellpincodes_db';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

2. Configure API keys in `backend/config/database.php`:
   - Mobile Money API credentials
   - SMS provider credentials
   - Security keys

### 3. Web Server Setup

#### Option 1: PHP Built-in Server (Development)
```bash
cd /path/to/project
php -S localhost:8000
```

#### Option 2: Apache/Nginx (Production)
- Configure virtual host to point to project directory
- Ensure PHP and MySQL are properly configured
- Set appropriate file permissions

### 4. Access the Application

- **Frontend**: `http://localhost:8000/`
- **Admin Dashboard**: `http://localhost:8000/admin.html`
- **API Documentation**: `http://localhost:8000/backend/api/`

## API Endpoints

### Public Endpoints

#### GET /backend/api/services.php
Get available services, exam types, and pricing information.

**Response:**
```json
{
  "success": true,
  "data": {
    "services": [...],
    "momo_providers": [...]
  }
}
```

#### POST /backend/api/purchase.php
Purchase checkers/vouchers.

**Request:**
```json
{
  "service_type_id": 1,
  "exam_type_id": 2,
  "quantity": 5,
  "momo_provider_id": 1,
  "phone_number": "0244123456"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Purchase completed successfully",
  "transaction_id": "TXN250128ABC123",
  "print_id": "PRT250128XYZ789",
  "checkers": {...},
  "sms_sent": true
}
```

#### POST /backend/api/retrieve.php
Retrieve old checkers using transaction/print ID.

**Request:**
```json
{
  "phone_number": "0244123456",
  "transaction_id": "TXN250128ABC123",
  "resend_sms": true
}
```

### Admin Endpoints

#### GET /backend/api/admin.php?action=dashboard
Get dashboard statistics (requires authentication).

**Headers:**
```
Authorization: Bearer admin-token-123
```

## Database Schema

### Key Tables

- **transactions** - Purchase records with payment status
- **checkers** - Generated vouchers/checkers
- **service_types** - Available services (WAEC, SHS, UCC)
- **exam_types** - Exam categories for each service
- **pricing_tiers** - Quantity-based pricing
- **momo_providers** - Mobile money providers
- **sms_logs** - SMS notification logs
- **payment_logs** - Payment processing logs

## Features in Detail

### Payment Processing
- Mock mobile money integration (easily replaceable with real APIs)
- Transaction tracking and status management
- Automatic payment verification
- Refund processing capability

### Checker Generation
- Unique serial numbers and PIN codes
- Automatic expiry date setting
- Bulk generation for multiple quantities
- Status tracking (active, used, expired)

### SMS Notifications
- Automatic SMS delivery after successful payment
- Resend capability for retrieval requests
- Delivery status tracking
- Bulk SMS support

### Admin Dashboard
- Real-time statistics and analytics
- Transaction monitoring
- Revenue tracking
- SMS delivery reports

## Security Features

- Input validation and sanitization
- SQL injection prevention using prepared statements
- XSS protection
- CORS configuration
- Error logging and monitoring

## Customization

### Adding New Services
1. Insert new service type in `service_types` table
2. Add exam types if needed in `exam_types` table
3. Configure pricing in `pricing_tiers` table
4. Update frontend forms as needed

### Payment Provider Integration
1. Update `PaymentProcessor.php` with real API calls
2. Configure webhook endpoints for payment notifications
3. Update database credentials and API keys

### SMS Provider Integration
1. Update `SMSHandler.php` with real SMS API
2. Configure SMS provider credentials
3. Customize message templates

## Production Deployment

### Security Checklist
- [ ] Change default database credentials
- [ ] Update JWT secret keys
- [ ] Configure HTTPS
- [ ] Set up proper error logging
- [ ] Configure backup systems
- [ ] Set up monitoring and alerts

### Performance Optimization
- [ ] Enable PHP OPcache
- [ ] Configure database indexing
- [ ] Set up CDN for static assets
- [ ] Implement caching strategies
- [ ] Optimize database queries

## Support

For technical support or customization requests, please refer to the code documentation or create an issue in the project repository.

## License

This project is created for educational and demonstration purposes. Please ensure compliance with all applicable laws and regulations when deploying in production.
