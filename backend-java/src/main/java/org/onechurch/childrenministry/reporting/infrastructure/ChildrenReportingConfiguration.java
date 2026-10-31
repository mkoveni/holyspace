package org.onechurch.childrenministry.reporting.infrastructure;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.reporting.application.AttendanceReporting;
import org.onechurch.childrenministry.reporting.application.contract.AttendanceReportReader;
import org.onechurch.childrenministry.reporting.application.contract.MinistryOverviewReader;
import org.onechurch.childrenministry.reporting.application.query.exportattendancereport.ExportAttendanceReportHandler;
import org.onechurch.childrenministry.reporting.application.query.getattendancereport.GetAttendanceReportHandler;
import org.onechurch.childrenministry.reporting.application.query.getattendancetimeline.GetAttendanceTimelineHandler;
import org.onechurch.childrenministry.reporting.application.query.getchildattendance.GetChildAttendanceHandler;
import org.onechurch.childrenministry.reporting.application.query.getministryoverview.GetMinistryOverviewHandler;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUp;
import org.onechurch.childrenministry.sessions.application.TeamPlanning;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class ChildrenReportingConfiguration {
    @Bean
    GetChildAttendanceHandler getChildAttendanceHandler(PermissionChecker authorization, AttendanceReader attendance) {
        return new GetChildAttendanceHandler(authorization, attendance);
    }
    @Bean
    AttendanceReporting attendanceReporting(AttendanceReportReader reader, PersonDirectory people, ZoneId zone,
        Clock clock) {
        return new AttendanceReporting(reader, people, zone, clock);
    }
    @Bean
    GetAttendanceReportHandler getAttendanceReportHandler(PermissionChecker authorization,
        AttendanceReporting reporting) {
        return new GetAttendanceReportHandler(authorization, reporting);
    }
    @Bean
    ExportAttendanceReportHandler exportAttendanceReportHandler(PermissionChecker authorization,
        AttendanceReporting reporting) {
        return new ExportAttendanceReportHandler(authorization, reporting);
    }
    @Bean
    GetAttendanceTimelineHandler getAttendanceTimelineHandler(PermissionChecker authorization,
        AttendanceReporting reporting) {
        return new GetAttendanceTimelineHandler(authorization, reporting);
    }
    @Bean
    GetMinistryOverviewHandler getMinistryOverviewHandler(PermissionChecker auth, MinistryOverviewReader overview,
        TeamPlanning teams, SafeguardingFollowUp safeguarding, IncidentReader incidents, DeliveryReader deliveries,
        Clock clock, ZoneId zone) {
        return new GetMinistryOverviewHandler(auth, overview, teams, safeguarding, incidents, deliveries, clock, zone);
    }
}
