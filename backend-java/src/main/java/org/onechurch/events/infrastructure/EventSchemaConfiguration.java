package org.onechurch.events.infrastructure;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import com.fasterxml.jackson.databind.JavaType;

import io.swagger.v3.core.converter.AnnotatedType;
import io.swagger.v3.core.converter.ModelConverter;
import io.swagger.v3.core.converter.ModelConverterContext;
import io.swagger.v3.oas.models.media.Schema;

import java.util.Iterator;
/** Keep nested Events model names distinct without documentation annotations in the application layer. */
@Configuration
public class EventSchemaConfiguration {
    @Bean
    ModelConverter eventSchemaNames() {
        return new ModelConverter() {
            @Override
            public Schema resolve(AnnotatedType type, ModelConverterContext context, Iterator<ModelConverter> chain) {
                Class<?> model = type.getType() instanceof Class<?> raw ? raw
                    : type.getType() instanceof JavaType javaType ? javaType.getRawClass() : null;
                if (model != null && model.getName().startsWith("org.onechurch.events.")
                    && !model.getName().contains(".presentation.")) {
                    type.name("Events" + model.getName()
                        .substring("org.onechurch.events.".length())
                        .replace(".", "_")
                        .replace("$", "_"));
                }
                return chain.hasNext() ? chain.next().resolve(type, context, chain) : null;
            }
        };
    }
}
