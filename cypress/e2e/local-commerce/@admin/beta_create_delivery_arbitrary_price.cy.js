context('Delivery (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')
    cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  it('[beta form] create delivery order with arbitrary price', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()
    
    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
      'Pickup comments'
    )

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
      'Dropoff comments'
    )

    cy.get(`[name="tasks[${1}].weight"]`).clear()
    cy.get(`[name="tasks[${1}].weight"]`).type(2.5)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('[name="delivery.override_price"]').check()

    cy.get('[name="variantName"]').clear()
    cy.get('[name="variantName"]').type('Test product')

    cy.get('#variantPriceVAT').clear()
    cy.get('#variantPriceVAT').type('72')

    cy.get('button[type="submit"]').click()

    // TODO : check for proper redirect when implemented
    // cy.location('pathname', { timeout: 10000 }).should(
    //   'match',
    //   /\/admin\/stores\/[0-9]+\/deliveries$/,
    // )

    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/deliveries$/,
    )

    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€72.00/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .should('exist')

    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .click()

    // Order page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/orders\/[0-9]+$/,
    )
    cy.get('[data-testid="name"]')
      .contains('Test product')

    cy.get('[data-testid="total"]')
      .contains('€72.00')
    })
})
