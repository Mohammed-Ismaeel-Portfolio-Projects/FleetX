const fs = require('fs');
const collection = {
  info: {
    name: 'FleetX Backend API Suite',
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    _postman_id: 'fleetx-backend-api-suite-0001'
  },
  variable: [
    { key: 'base_url', value: 'http://localhost:8000' },
    { key: 'token', value: '' },
    { key: 'company_id', value: '' },
    { key: 'driver_id', value: '' },
    { key: 'vehicle_id', value: '' },
    { key: 'trailer_id', value: '' },
    { key: 'shipment_id', value: '' },
    { key: 'assignment_id', value: '' },
    { key: 'status_id', value: '' },
    { key: 'user_id', value: '' }
  ],
  item: []
};

function request(name, method, url, body = null, tests = '', auth = true) {
  const item = {
    name,
    request: {
      method,
      header: [
        { key: 'Content-Type', value: 'application/json', type: 'text' }
      ],
      url: {
        raw: url,
        host: ['{{base_url}}'],
        path: url.replace('{{base_url}}', '').split('/').filter(Boolean)
      }
    }
  };
  if (auth) {
    item.request.header.push({ key: 'Authorization', value: 'Bearer {{token}}', type: 'text' });
  }
  if (body !== null) {
    item.request.body = { mode: 'raw', raw: JSON.stringify(body, null, 2) };
  }
  if (tests) {
    item.event = [{ listen: 'test', script: { type: 'text/javascript', exec: tests.split('\n') } }];
  }
  return item;
}

function folder(name, items) {
  return { name, item: items };
}

const auth = [
  request('Register', 'POST', '{{base_url}}/api/v1/auth/register', {
    name: 'Test User',
    email: 'test.user@example.com',
    password: 'Password123',
    password_confirmation: 'Password123'
  }, [
    "pm.test('Status code is 201', function () { pm.response.to.have.status(201); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('access_token');",
    "pm.environment.set('token', json.data.access_token);",
    "pm.environment.set('user_id', json.data.user.id);"
  ].join('\n'), false),
  request('Login', 'POST', '{{base_url}}/api/v1/auth/login', {
    email: 'test.user@example.com',
    password: 'Password123'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('access_token');",
    "pm.environment.set('token', json.data.access_token);"
  ].join('\n'), false),
  request('Me', 'GET', '{{base_url}}/api/v1/auth/me', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id');"
  ].join('\n')),
  request('Refresh Token', 'POST', '{{base_url}}/api/v1/auth/refresh', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('access_token');",
    "pm.environment.set('token', json.data.access_token);"
  ].join('\n')),
  request('Logout', 'POST', '{{base_url}}/api/v1/auth/logout', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('message');"
  ].join('\n'))
];

const companies = [
  request('Create Company', 'POST', '{{base_url}}/api/v1/companies', {
    company_name: 'Test Company',
    legal_name: 'Test Company LLC',
    company_code: 'TC001',
    primary_email: 'contact@testcompany.com',
    country: 'US',
    city: 'New York',
    address_line_1: '123 Main St',
    postal_code: '10001',
    currency_code: 'USD',
    status_id: 1
  }, [
    "pm.test('Status code is 201', function () { pm.response.to.have.status(201); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id');",
    "pm.environment.set('company_id', json.data.id);"
  ].join('\n')),
  request('Create Company - Validation Error', 'POST', '{{base_url}}/api/v1/companies', {
    legal_name: 'Missing Name Co',
    company_code: 'TC002'
  }, [
    "pm.test('Status code is 422', function () { pm.response.to.have.status(422); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', false);",
    "pm.expect(json).to.have.property('errors');"
  ].join('\n')),
  request('Get All Companies', 'GET', '{{base_url}}/api/v1/companies', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Get Company Details', 'GET', '{{base_url}}/api/v1/companies/{{company_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id', pm.environment.get('company_id'));"
  ].join('\n')),
  request('Update Company', 'PUT', '{{base_url}}/api/v1/companies/{{company_id}}', {
    company_name: 'Updated Test Company',
    primary_email: 'updated@testcompany.com'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Toggle Company Status', 'PATCH', '{{base_url}}/api/v1/companies/{{company_id}}/status', {
    is_active: true
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Delete Company', 'DELETE', '{{base_url}}/api/v1/companies/{{company_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Restore Company', 'PATCH', '{{base_url}}/api/v1/companies/{{company_id}}/restore', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n'))
];

const vehicles = [
  request('Create Vehicle', 'POST', '{{base_url}}/api/v1/vehicles', {
    operational_code: 'VEH-001',
    fleet_number: 'FLEET-001',
    plate_number: 'ABC123',
    manufacturer: 'Volvo',
    model: 'FH16',
    vehicle_type_id: 1,
    fuel_type_id: 1,
    status_id: 1
  }, [
    "pm.test('Status code is 201', function () { pm.response.to.have.status(201); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id');",
    "pm.environment.set('vehicle_id', json.data.id);"
  ].join('\n')),
  request('Create Vehicle - Validation Error', 'POST', '{{base_url}}/api/v1/vehicles', {
    operational_code: 'VEH-002'
  }, [
    "pm.test('Status code is 422', function () { pm.response.to.have.status(422); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', false);",
    "pm.expect(json).to.have.property('errors');"
  ].join('\n')),
  request('Get All Vehicles', 'GET', '{{base_url}}/api/v1/vehicles', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Get Vehicle Details', 'GET', '{{base_url}}/api/v1/vehicles/{{vehicle_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id', pm.environment.get('vehicle_id'));"
  ].join('\n')),
  request('Update Vehicle', 'PUT', '{{base_url}}/api/v1/vehicles/{{vehicle_id}}', {
    model: 'FH16 Updated'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Toggle Vehicle Status', 'PATCH', '{{base_url}}/api/v1/vehicles/{{vehicle_id}}/status', {
    is_active: true
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Delete Vehicle', 'DELETE', '{{base_url}}/api/v1/vehicles/{{vehicle_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Restore Vehicle', 'PATCH', '{{base_url}}/api/v1/vehicles/{{vehicle_id}}/restore', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n'))
];

const trailers = [
  request('Create Trailer', 'POST', '{{base_url}}/api/v1/trailers', {
    trailer_code: 'TRL-001',
    fleet_number: 'T-001',
    plate_number: 'TR123',
    trailer_type: 'dry',
    manufacturer: 'Schmitz',
    status_id: 1
  }, [
    "pm.test('Status code is 201', function () { pm.response.to.have.status(201); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id');",
    "pm.environment.set('trailer_id', json.data.id);"
  ].join('\n')),
  request('Get All Trailers', 'GET', '{{base_url}}/api/v1/trailers', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Get Trailer Details', 'GET', '{{base_url}}/api/v1/trailers/{{trailer_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id', pm.environment.get('trailer_id'));"
  ].join('\n')),
  request('Update Trailer', 'PUT', '{{base_url}}/api/v1/trailers/{{trailer_id}}', {
    manufacturer: 'Schmitz Cargobull'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Toggle Trailer Status', 'PATCH', '{{base_url}}/api/v1/trailers/{{trailer_id}}/status', {
    is_active: true
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Delete Trailer', 'DELETE', '{{base_url}}/api/v1/trailers/{{trailer_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Restore Trailer', 'PATCH', '{{base_url}}/api/v1/trailers/{{trailer_id}}/restore', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n'))
];

const drivers = [
  request('Create Driver', 'POST', '{{base_url}}/api/v1/drivers', {
    employee_number: 'DRV001',
    first_name: 'John',
    last_name: 'Doe',
    email: 'john.doe@example.com',
    phone: '555-1234',
    nationality: 'US',
    date_of_birth: '1985-04-12',
    employment_date: '2020-01-10',
    license_number: 'L12345',
    license_class: 'A',
    license_expiry_date: '2026-12-31',
    medical_certificate_expiry: '2026-12-31',
    status_id: 1
  }, [
    "pm.test('Status code is 201', function () { pm.response.to.have.status(201); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id');",
    "pm.environment.set('driver_id', json.data.id);"
  ].join('\n')),
  request('Create Driver - Validation Error', 'POST', '{{base_url}}/api/v1/drivers', {
    first_name: 'Jane'
  }, [
    "pm.test('Status code is 422', function () { pm.response.to.have.status(422); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', false);",
    "pm.expect(json).to.have.property('errors');"
  ].join('\n')),
  request('Get All Drivers', 'GET', '{{base_url}}/api/v1/drivers', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Get Driver Details', 'GET', '{{base_url}}/api/v1/drivers/{{driver_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id', pm.environment.get('driver_id'));"
  ].join('\n')),
  request('Update Driver', 'PUT', '{{base_url}}/api/v1/drivers/{{driver_id}}', {
    phone: '555-4321'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Toggle Driver Status', 'PATCH', '{{base_url}}/api/v1/drivers/{{driver_id}}/status', {
    is_active: true
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Delete Driver', 'DELETE', '{{base_url}}/api/v1/drivers/{{driver_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Restore Driver', 'PATCH', '{{base_url}}/api/v1/drivers/{{driver_id}}/restore', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n'))
];

const shipments = [
  request('Create Shipment', 'POST', '{{base_url}}/api/v1/shipments', {
    customer_company_id: '{{company_id}}',
    priority: 'normal',
    status_id: 1,
    cargo_type: 'General',
    pickup_company: 'Sender Inc.',
    delivery_company: 'Receiver LLC',
    planned_pickup_date: '2026-07-10',
    planned_delivery_date: '2026-07-12'
  }, [
    "pm.test('Status code is 201', function () { pm.response.to.have.status(201); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id');",
    "pm.environment.set('shipment_id', json.data.id);"
  ].join('\n')),
  request('Create Shipment - Validation Error', 'POST', '{{base_url}}/api/v1/shipments', {
    priority: 'normal'
  }, [
    "pm.test('Status code is 422', function () { pm.response.to.have.status(422); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', false);",
    "pm.expect(json).to.have.property('errors');"
  ].join('\n')),
  request('Get All Shipments', 'GET', '{{base_url}}/api/v1/shipments', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Get Shipment Details', 'GET', '{{base_url}}/api/v1/shipments/{{shipment_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id', pm.environment.get('shipment_id'));"
  ].join('\n')),
  request('Update Shipment', 'PUT', '{{base_url}}/api/v1/shipments/{{shipment_id}}', {
    notes: 'Updated shipment notes.'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Change Shipment Status', 'PATCH', '{{base_url}}/api/v1/shipments/{{shipment_id}}/status', {
    status_id: 1,
    actual_pickup_date: '2026-07-10'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Delete Shipment', 'DELETE', '{{base_url}}/api/v1/shipments/{{shipment_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Restore Shipment', 'PATCH', '{{base_url}}/api/v1/shipments/{{shipment_id}}/restore', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n'))
];

const assignments = [
  request('Create Assignment', 'POST', '{{base_url}}/api/v1/assignments', {
    shipment_id: '{{shipment_id}}',
    assignment_code: 'ASN-001',
    driver_id: '{{driver_id}}',
    vehicle_id: '{{vehicle_id}}',
    trailer_id: '{{trailer_id}}',
    assigned_by: '{{user_id}}',
    status_id: 1,
    assigned_at: '2026-07-05T08:00:00Z'
  }, [
    "pm.test('Status code is 201', function () { pm.response.to.have.status(201); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id');",
    "pm.environment.set('assignment_id', json.data.id);"
  ].join('\n')),
  request('Create Assignment - Validation Error', 'POST', '{{base_url}}/api/v1/assignments', {
    assignment_code: 'ASN-002'
  }, [
    "pm.test('Status code is 422', function () { pm.response.to.have.status(422); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', false);",
    "pm.expect(json).to.have.property('errors');"
  ].join('\n')),
  request('Get All Assignments', 'GET', '{{base_url}}/api/v1/assignments', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Get Assignment Details', 'GET', '{{base_url}}/api/v1/assignments/{{assignment_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);",
    "pm.expect(json.data).to.have.property('id', pm.environment.get('assignment_id'));"
  ].join('\n')),
  request('Update Assignment', 'PUT', '{{base_url}}/api/v1/assignments/{{assignment_id}}', {
    notes: 'Updated assignment notes.'
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Change Assignment Status', 'PATCH', '{{base_url}}/api/v1/assignments/{{assignment_id}}/status', {
    status_id: 1
  }, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Delete Assignment', 'DELETE', '{{base_url}}/api/v1/assignments/{{assignment_id}}', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n')),
  request('Restore Assignment', 'PATCH', '{{base_url}}/api/v1/assignments/{{assignment_id}}/restore', null, [
    "pm.test('Status code is 200', function () { pm.response.to.have.status(200); });",
    "var json = pm.response.json();",
    "pm.expect(json).to.have.property('success', true);"
  ].join('\n'))
];

collection.item.push(folder('Authentication', auth));
collection.item.push(folder('Companies', companies));
collection.item.push(folder('Vehicles', vehicles));
collection.item.push(folder('Trailers', trailers));
collection.item.push(folder('Drivers', drivers));
collection.item.push(folder('Shipments', shipments));
collection.item.push(folder('Assignments', assignments));

fs.writeFileSync('fleetx_postman_collection.json', JSON.stringify(collection, null, 2));
console.log('done');
