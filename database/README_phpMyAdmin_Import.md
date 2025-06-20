# phpMyAdmin Database Import Guide

## 🚀 **msbCORE Enhanced Workshop Database Schema**
**Version:** 3.2.0 - Body Repair & Paint Workshop  
**Compatible with:** PHP 8.2.6, MySQL 5.7.34

---

## 📋 **IMPORT INSTRUCTIONS FOR phpMyAdmin**

### **Step 1: Create Database**
1. Open phpMyAdmin in your browser
2. Click on "Databases" tab
3. Create new database with name: `msbcore_bengkel`
4. Set collation to: `utf8mb4_unicode_ci`

### **Step 2: Import Schema**
1. Select the `msbcore_bengkel` database
2. Click on "Import" tab
3. Choose file: `schema_phpmyadmin.sql`
4. Click "Go" to import

### **Step 3: Verify Import**
After successful import, you should see **17 tables**:

#### **Core System Tables:**
- `users` - User management and authentication
- `customers` - Customer information
- `vehicles` - Vehicle records
- `services` - Service catalog
- `work_orders` - Work order management

#### **Security & Activity Tables:**
- `remember_tokens` - Remember me functionality
- `login_attempts` - Login security tracking
- `user_activities` - User activity logging
- `notifications` - System notifications

#### **Inventory Management:**
- `inventory_categories` - Inventory categorization
- `inventory` - General inventory items

#### **🏭 Enhanced Workshop Tables:**
- `damage_assessments` - Vehicle damage evaluation
- `paint_jobs` - Paint work management
- `body_repair_tasks` - Body repair task tracking
- `paint_materials` - Specialized paint inventory
- `workshop_bays` - Workshop bay management
- `quality_inspections` - Quality control system
- `material_usage` - Material usage tracking

---

## 🔧 **TROUBLESHOOTING COMMON IMPORT ISSUES**

### **Issue 1: JSON Column Type Error**
**Problem:** MySQL version doesn't support JSON  
**Solution:** JSON fields are converted to TEXT with comments in the phpMyAdmin version

### **Issue 2: Foreign Key Constraints**
**Problem:** Foreign key constraint errors  
**Solution:** Tables are ordered correctly in the schema file

### **Issue 3: Character Set Issues**
**Problem:** Character encoding problems  
**Solution:** All tables use `utf8mb4_unicode_ci` collation

### **Issue 4: Transaction Errors**
**Problem:** Transaction statements cause errors  
**Solution:** Transaction statements removed from phpMyAdmin version

---

## 📊 **SAMPLE DATA INCLUDED**

### **Workshop Bays (7 bays):**
- **BR01, BR02:** Body Repair Bays
- **PB01, PB02:** Paint Booths with climate control
- **DB01:** Drying Bay with infrared lamps
- **PREP01:** Preparation Bay
- **QC01:** Quality Control Bay

### **Paint Materials (7 items):**
- Primers, Base Coats, Clear Coats
- Thinners, Hardeners
- Various colors (White, Black, Silver Metallic)

### **Default Admin User:**
- **Username:** admin
- **Email:** admin@msbcore.com
- **Password:** admin123
- **Role:** admin

---

## 🎯 **POST-IMPORT VERIFICATION**

### **1. Check Table Count**
```sql
SELECT COUNT(*) as table_count 
FROM information_schema.tables 
WHERE table_schema = 'msbcore_bengkel';
```
**Expected Result:** 17 tables

### **2. Verify Sample Data**
```sql
SELECT COUNT(*) as workshop_bays FROM workshop_bays;
SELECT COUNT(*) as paint_materials FROM paint_materials;
SELECT COUNT(*) as admin_users FROM users WHERE role = 'admin';
```
**Expected Results:** 7 bays, 7 materials, 1 admin user

### **3. Test Foreign Key Relationships**
```sql
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = 'msbcore_bengkel'
ORDER BY TABLE_NAME;
```

---

## 🔐 **SECURITY NOTES**

1. **Change Default Password:** Immediately change the admin password after import
2. **Database User:** Create a dedicated MySQL user for the application
3. **Permissions:** Grant only necessary permissions to the application user
4. **Backup:** Create a backup after successful import

---

## 🚀 **NEXT STEPS AFTER IMPORT**

1. **Update Configuration:** Update `config/config.php` with correct database credentials
2. **Test Connection:** Run the application to verify database connectivity
3. **Create Users:** Add additional users through the admin panel
4. **Configure Workshop:** Set up additional workshop bays and materials as needed

---

## 📞 **SUPPORT**

If you encounter any issues during import:
1. Check MySQL version compatibility (5.7.34+ recommended)
2. Verify phpMyAdmin version (4.9+ recommended)
3. Ensure sufficient database privileges
4. Check error logs for specific error messages

---

## ✅ **IMPORT SUCCESS CHECKLIST**

- [ ] Database `msbcore_bengkel` created
- [ ] All 17 tables imported successfully
- [ ] Sample workshop bays data loaded (7 bays)
- [ ] Sample paint materials data loaded (7 items)
- [ ] Admin user created successfully
- [ ] Foreign key constraints working
- [ ] No import errors in phpMyAdmin
- [ ] Application can connect to database

**🎉 Import Complete! Your enhanced workshop management system is ready to use.**
