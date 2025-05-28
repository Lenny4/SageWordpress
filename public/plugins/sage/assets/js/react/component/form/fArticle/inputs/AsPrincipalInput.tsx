import * as React from "react";
import { useImperativeHandle } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";

let translations: any = getTranslations();

export type AsPrincipalInputState = {
  defaultDeNo: string;
  deNo: number | string;
  onAsPrincipalChangedParent: (name: string, newValue: string | number) => void;
};

export const AsPrincipalInput = React.forwardRef(
  (
    { defaultDeNo, deNo, onAsPrincipalChangedParent }: AsPrincipalInputState,
    ref,
  ) => {
    const [selectedDeNo, setSelectedDeNo] = React.useState<string>(defaultDeNo);
    const name = `_sage_fArtstocks[${deNo}].asPrincipal`;

    useImperativeHandle(ref, () => ({
      onAsPrincipalChanged(newDeNo: string) {
        setSelectedDeNo(newDeNo);
      },
    }));

    return (
      <>
        <label htmlFor={name} style={{ display: "none" }}>
          <Tooltip title={name} arrow placement="top">
            <span>{translations.words.supplierRef}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "center" }}>
          <input
            id={name}
            name={name}
            type="checkbox"
            checked={selectedDeNo.toString() === deNo.toString()}
            onChange={(e) => {
              if (e.target.checked) {
                onAsPrincipalChangedParent(name, deNo);
              }
            }}
          />
        </div>
      </>
    );
  },
);
