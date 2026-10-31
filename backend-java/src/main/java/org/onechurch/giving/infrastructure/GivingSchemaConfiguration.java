package org.onechurch.giving.infrastructure;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import com.fasterxml.jackson.databind.JavaType;

import io.swagger.v3.core.converter.AnnotatedType;
import io.swagger.v3.core.converter.ModelConverter;
import io.swagger.v3.core.converter.ModelConverterContext;
import io.swagger.v3.oas.models.media.Schema;

import java.util.Iterator;
@Configuration
public class GivingSchemaConfiguration {
    @Bean
    ModelConverter givingSchemaNames() {
        return new ModelConverter() {
            @Override
            public Schema resolve(AnnotatedType type, ModelConverterContext context, Iterator<ModelConverter> chain) {
                Class<?> model = type.getType() instanceof Class<?> raw ? raw
                    : type.getType() instanceof JavaType javaType ? javaType.getRawClass() : null;
                if (model != null && (model.getName().startsWith("org.onechurch.giving.")
                    || model.getName().startsWith("org.onechurch.payments."))) {
                    type.name(model.getName().replace("org.onechurch.", "").replace(".", "_").replace("$", "_"));
                }
                return chain.hasNext() ? chain.next().resolve(type, context, chain) : null;
            }
        };
    }
}
