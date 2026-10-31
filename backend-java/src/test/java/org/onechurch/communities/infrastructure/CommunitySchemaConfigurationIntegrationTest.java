package org.onechurch.communities.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.CommunityIntegrationTestSupport;

import java.util.Map;
class CommunitySchemaConfigurationIntegrationTest extends CommunityIntegrationTestSupport {
    @Test
    void staffingAndDeliverySummariesHaveDistinctPublishedSchemas() throws Exception {
        Response response = request("GET", "/v3/api-docs", null, null);
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(200);
        Map<?, ?> components = (Map<?, ?>) response.data().get("components"),
            schemas = (Map<?, ?>) components.get("schemas");
        Map<?, ?> staffing = (Map<?, ?>) schemas.get("volunteering_application_contract_ActivityStaffing_Summary"),
            delivery = (Map<?, ?>) schemas.get("communications_application_contract_DeliveryReader_Summary");
        assertThat(staffing).isNotNull();
        assertThat(delivery).isNotNull();
        Map<?, ?> staffingProperties = (Map<?, ?>) staffing.get("properties"),
            deliveryProperties = (Map<?, ?>) delivery.get("properties");
        assertThat(staffingProperties.get("positions")).isNotNull();
        assertThat(staffingProperties.get("communicationId")).isNull();
        assertThat(deliveryProperties.get("communicationId")).isNotNull();
        assertThat(deliveryProperties.get("positions")).isNull();
    }
}
