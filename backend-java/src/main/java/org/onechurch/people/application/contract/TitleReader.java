package org.onechurch.people.application.contract;

import java.util.List;

public interface TitleReader {
    record Title(String code, String label) {}
    List<Title> titles();
    boolean exists(String code);
}
