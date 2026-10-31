package org.onechurch;

import static com.tngtech.archunit.lang.syntax.ArchRuleDefinition.noClasses;

import org.jooq.PlainSQL;

import com.tngtech.archunit.core.domain.Dependency;
import com.tngtech.archunit.core.domain.JavaClass;
import com.tngtech.archunit.core.domain.JavaFieldAccess;
import com.tngtech.archunit.core.domain.JavaMethodCall;
import com.tngtech.archunit.core.importer.ImportOption;
import com.tngtech.archunit.junit.AnalyzeClasses;
import com.tngtech.archunit.junit.ArchTest;
import com.tngtech.archunit.lang.ArchCondition;
import com.tngtech.archunit.lang.ArchRule;
import com.tngtech.archunit.lang.ConditionEvents;
import com.tngtech.archunit.lang.SimpleConditionEvent;
import com.tngtech.archunit.lang.syntax.ArchRuleDefinition;

import java.util.Set;

@AnalyzeClasses(packages = "org.onechurch", importOptions = ImportOption.DoNotIncludeTests.class)
class ArchitectureTests {

    @ArchTest static final ArchRule paymentsUsePublishedForeignContracts = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.payments..")
        .should(new ArchCondition<JavaClass>("use published foreign contracts") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if (target.startsWith("org.onechurch.") && !target.startsWith("org.onechurch.payments.")
                        && !target.startsWith("org.onechurch.shared.") && !target.contains(".application.contract")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });

    @ArchTest static final ArchRule givingUsesPublishedForeignContracts = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.giving..")
        .should(new ArchCondition<JavaClass>("use published foreign contracts") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if (target.startsWith("org.onechurch.") && !target.startsWith("org.onechurch.giving.")
                        && !target.startsWith("org.onechurch.shared.") && !target.contains(".application.contract")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });

    @ArchTest static final ArchRule communitiesUsePublishedForeignContracts = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.communities..")
        .should(new ArchCondition<JavaClass>("use published foreign contracts") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if (target.startsWith("org.onechurch.") && !target.startsWith("org.onechurch.communities.")
                        && !target.startsWith("org.onechurch.shared.") && !target.contains(".application.contract")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });

    @ArchTest static final ArchRule eventsUsePublishedForeignContracts = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.events..")
        .should(new ArchCondition<JavaClass>("use published foreign contracts") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if (target.startsWith("org.onechurch.") && !target.startsWith("org.onechurch.events.")
                        && !target.startsWith("org.onechurch.shared.") && !target.contains(".application.contract")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });

    @ArchTest static final ArchRule domainIsIndependent = noClasses().that()
        .resideInAPackage("..domain..")
        .should()
        .dependOnClassesThat()
        .resideInAnyPackage("..application..", "..infrastructure..", "..presentation..", "org.springframework..",
            "org.jooq..", "jakarta..");

    @ArchTest static final ArchRule applicationIsIndependent = noClasses().that()
        .resideInAPackage("..application..")
        .should()
        .dependOnClassesThat()
        .resideInAnyPackage("..infrastructure..", "..presentation..", "org.springframework..", "org.jooq..",
            "jakarta..");

    @ArchTest static final ArchRule queriesCannotWrite = noClasses().that()
        .resideInAPackage("..application.query..")
        .should()
        .dependOnClassesThat()
        .haveSimpleNameEndingWith("Writer");

    @ArchTest static final ArchRule queriesCannotRunCommands = noClasses().that()
        .resideInAPackage("..application.query..")
        .should()
        .dependOnClassesThat()
        .resideInAnyPackage("..application.command..", "org.onechurch.shared.application");

    @ArchTest static final ArchRule controllersCannotUseAdapters = noClasses().that()
        .resideInAPackage("..presentation..")
        .should()
        .dependOnClassesThat()
        .resideInAnyPackage("..infrastructure..", "org.jooq..");

    @ArchTest static final ArchRule controllersCannotWriteDirectly = noClasses().that()
        .resideInAPackage("..presentation..")
        .should()
        .dependOnClassesThat()
        .haveSimpleNameEndingWith("Writer");

    @ArchTest static final ArchRule queriesCannotUseAggregateRepositories = noClasses().that()
        .resideInAPackage("..application.query..")
        .should()
        .dependOnClassesThat()
        .resideInAPackage("..domain.repository..");

    @ArchTest static final ArchRule controllersCannotUseAggregateRepositories = noClasses().that()
        .resideInAPackage("..presentation..")
        .should()
        .dependOnClassesThat()
        .resideInAPackage("..domain.repository..");

    @ArchTest static final ArchRule sharedCannotDependOnBusinessContexts = noClasses().that()
        .resideInAPackage("org.onechurch.shared.application..")
        .should()
        .dependOnClassesThat()
        .resideInAPackage("org.onechurch.identityaccess..");

    @ArchTest static final ArchRule volunteeringOnlyUsesPublishedForeignContracts = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.volunteering..")
        .should(new ArchCondition<JavaClass>("use only published contracts of other bounded contexts") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if ((target.startsWith("org.onechurch.people.")
                        || target.startsWith("org.onechurch.identityaccess.")
                        || target.startsWith("org.onechurch.childrenministry.")
                        || target.startsWith("org.onechurch.communications.")
                        || target.startsWith("org.onechurch.events.")) && !target.contains(".application.contract")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });
    @ArchTest static final ArchRule childrenUsePublishedVolunteeringContracts = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.childrenministry..")
        .should(new ArchCondition<JavaClass>("use published volunteer staffing contracts") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if (target.startsWith("org.onechurch.volunteering.")
                        && !target.startsWith("org.onechurch.volunteering.application.contract")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });
    @ArchTest static final ArchRule communicationsCannotDependOnChildren = noClasses().that()
        .resideInAPackage("org.onechurch.communications..")
        .should()
        .dependOnClassesThat()
        .resideInAPackage("org.onechurch.childrenministry..");

    @ArchTest static final ArchRule childrenUsePublishedCommunicationsContracts = noClasses().that()
        .resideInAPackage("org.onechurch.childrenministry..")
        .should()
        .dependOnClassesThat()
        .resideInAnyPackage("org.onechurch.communications.domain..", "org.onechurch.communications.infrastructure..",
            "org.onechurch.communications.presentation..", "org.onechurch.communications.application.command..",
            "org.onechurch.communications.application.query..");

    @ArchTest static final ArchRule communicationContractsDoNotExposeAggregates = noClasses().that()
        .resideInAPackage("org.onechurch.communications.application.contract..")
        .should()
        .dependOnClassesThat()
        .resideInAPackage("org.onechurch.communications.domain..");

    @ArchTest static final ArchRule childrenOnlyUseCommunicationsContracts = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.childrenministry..")
        .should(new ArchCondition<JavaClass>("integrate with Communications through published contracts") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if (target.startsWith("org.onechurch.communications.")
                        && !target.startsWith("org.onechurch.communications.application.contract")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });

    @ArchTest static final ArchRule moduleAdaptersStayPrivate = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.childrenministry..")
        .should(new ArchCondition<JavaClass>("use contracts instead of another module's adapters") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                String own = type.getPackageName().split("\\.")[3];
                for (Dependency dependency : type.getDirectDependenciesFromSelf()) {
                    String target = dependency.getTargetClass().getPackageName();
                    if (target.startsWith("org.onechurch.childrenministry.") && target.contains(".infrastructure")
                        && !target.startsWith("org.onechurch.childrenministry.shared.")
                        && !target.startsWith("org.onechurch.childrenministry." + own + ".")) {
                        events.add(SimpleConditionEvent.violated(dependency, dependency.getDescription()));
                    }
                }
            }
        });

    @ArchTest static final ArchRule deliveryTablesBelongToCommunications = ArchRuleDefinition.classes()
        .that()
        .resideInAPackage("org.onechurch.childrenministry..")
        .should(new ArchCondition<JavaClass>("never access Communications tables") {
            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (JavaFieldAccess access : type.getFieldAccessesFromSelf()) {
                    if (access.getTargetOwner().getPackageName().startsWith("org.onechurch.shared.infrastructure.jooq")
                        && (access.getName().startsWith("COMMUNICATION_")
                            || access.getTargetOwner().getSimpleName().startsWith("Communication"))) {
                        events.add(SimpleConditionEvent.violated(access, access.getDescription()));
                    }
                }
            }
        });

    @ArchTest static final ArchRule databaseAccessMustUseTypedJooq = ArchRuleDefinition.classes(). // The generator
                                                                                                   // emits jOOQ API
                                                                                                   // overloads and SQL
                                                                                                   // defaults from
                                                                                                   // migrations.
                                                                                                   // Enforce this rule
                                                                                                   // on
                                                                                                   // application-owned
                                                                                                   // code, not
                                                                                                   // generated library
                                                                                                   // scaffolding.
        that()
        .resideOutsideOfPackage("org.onechurch.shared.infrastructure.jooq..")
        .should(new ArchCondition<JavaClass>("use generated schema types and avoid jOOQ plain SQL APIs") {

            @Override
            public void check(JavaClass type, ConditionEvents events) {
                for (JavaMethodCall call : type.getMethodCallsFromSelf()) {
                    boolean plainSql = call.getTarget()
                        .resolveMember()
                        .map(method -> method.isAnnotatedWith(PlainSQL.class))
                        .orElse(false);
                    boolean dynamicSchemaLookup = call.getTargetOwner().getName().equals("org.jooq.impl.DSL")
                        && Set.of("field", "table").contains(call.getName())
                        && !call.getTarget().getRawParameterTypes().isEmpty()
                        && Set.of("java.lang.String", "org.jooq.Name")
                            .contains(call.getTarget().getRawParameterTypes().getFirst().getName());
                    if (plainSql || dynamicSchemaLookup) {
                        events.add(SimpleConditionEvent.violated(call,
                            call.getDescription() + " bypasses generated schema types"));
                    }
                }
            }
        });
}
