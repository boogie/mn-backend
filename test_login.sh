#!/bin/bash
curl -X POST "http://localhost:8000/api/auth.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test123"}'
