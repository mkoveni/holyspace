package org.onechurch.giving.infrastructure;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.GivingIntegrationTestSupport;

import java.util.Map;

class GivingSchemaConfigurationIntegrationTest extends GivingIntegrationTestSupport {
    @Test
    void moneyInputsAreDocumentedAsIntegerMinorUnitsAndTotalsAsStrings() throws Exception {
        Response response = request("GET", "/v3/api-docs", null, null);
        assertThat(response.status()).isEqualTo(200);
        Map<?, ?> components = (Map<?, ?>) response.data().get("components");
        Map<?, ?> schemas = (Map<?, ?>) components.get("schemas");
        for (String name : java.util.List.of("giving_presentation_api_GivingApi_RecordGiftBody",
            "giving_presentation_api_GivingApi_AllocationBody", "giving_presentation_api_PledgeApi_CreatePledgeBody",
            "giving_presentation_api_PledgeApi_AssignBody")) {
            Map<?, ?> schema = (Map<?, ?>) schemas.get(name);
            Map<?, ?> properties = (Map<?, ?>) schema.get("properties");
            Map<?, ?> amount = (Map<?, ?>) properties.get("amountMinor");
            assertThat(amount.get("type")).isEqualTo("integer");
            assertThat(amount.get("format")).isEqualTo("int64");
        }
        Map<?, ?> total = (Map<?, ?>) schemas.get("giving_application_contract_GivingReader_Total");
        Map<?, ?> totals = (Map<?, ?>) total.get("properties");
        assertThat(((Map<?, ?>) totals.get("netMinor")).get("type")).isEqualTo("string");
    }
}
